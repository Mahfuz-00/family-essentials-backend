<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Payments\GatewayCredentials;
use App\Payments\PaymentGatewayManager;
use App\Tenancy\Tenancy;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PaddlePaymentTest extends TestCase
{
    use CreatesTenants;

    /** Full-length fake Paddle keys: 69 characters, as the real format requires. */
    private const SANDBOX_KEY = 'pdl_sdbx_apikey_'.'aaaaaaaaaaaaaaaaaaaaaaaaaa_bbbbbbbbbbbbbbbbbbbbbb_ccc';

    private const LIVE_KEY = 'pdl_live_apikey_'.'aaaaaaaaaaaaaaaaaaaaaaaaaa_bbbbbbbbbbbbbbbbbbbbbb_ccc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paddle.api_key', self::SANDBOX_KEY);
        config()->set('services.paddle.webhook_secret', 'pdl_ntfset_test_secret');
        config()->set('services.paddle.price_id', 'pri_test_family');
        config()->set('services.paddle.test_price_id', 'pri_test_oneoff');
        config()->set('services.paddle.client_token', 'test_client_token_fixture');
    }

    public function test_checkout_creates_a_paddle_transaction_with_the_configured_price(): void
    {
        Http::fake([
            'sandbox-api.paddle.com/transactions' => Http::response([
                'data' => [
                    'id' => 'txn_01',
                    'status' => 'ready',
                    'checkout' => ['url' => 'https://pay.paddle.io/txn_01'],
                ],
            ], 201),
        ]);

        $tenant = $this->createTenant('Paddle Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'gateway' => 'paddle',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.gateway', 'paddle')
            ->assertJsonPath('data.checkout.session_id', 'txn_01')
            ->assertJsonPath('data.checkout.mode', 'sandbox')
            ->assertJsonPath('data.checkout.url', 'https://pay.paddle.io/txn_01');

        $invoiceId = $response->json('data.invoice.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'pending',
            'payment_method' => 'paddle',
        ]);

        Http::assertSent(function ($request) use ($invoiceId): bool {
            return $request->url() === 'https://sandbox-api.paddle.com/transactions'
                && in_array('Bearer '.self::SANDBOX_KEY, $request->header('Authorization'), true)
                && $request['items'][0]['price_id'] === 'pri_test_family'
                && $request['items'][0]['quantity'] === 1
                && $request['collection_mode'] === 'automatic'
                && $request['custom_data']['invoice_id'] === (string) $invoiceId;
        });
    }

    public function test_a_product_id_resolves_to_its_first_active_price(): void
    {
        config()->set('services.paddle.price_id', 'pro_family_123');

        Http::fake([
            'sandbox-api.paddle.com/prices*' => Http::response([
                'data' => [['id' => 'pri_resolved_family', 'status' => 'active']],
            ], 200),
            'sandbox-api.paddle.com/transactions' => Http::response([
                'data' => [
                    'id' => 'txn_product',
                    'status' => 'ready',
                    'checkout' => ['url' => 'https://pay.paddle.io/txn_product'],
                ],
            ], 201),
        ]);

        $tenant = $this->createTenant('Paddle Product Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'gateway' => 'paddle',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.session_id', 'txn_product');

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://sandbox-api.paddle.com/prices')
            && $request['product_id'] === 'pro_family_123');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://sandbox-api.paddle.com/transactions'
            && $request['items'][0]['price_id'] === 'pri_resolved_family');
    }

    public function test_a_sandbox_product_without_a_price_gets_a_test_price(): void
    {
        config()->set('services.paddle.price_id', 'pro_empty_123');

        Http::fake(function (HttpRequest $request) {
            $url = $request->url();

            if ($url === 'https://sandbox-api.paddle.com/prices' && $request->method() === 'POST') {
                return Http::response(['data' => ['id' => 'pri_created']], 201);
            }

            if (str_starts_with($url, 'https://sandbox-api.paddle.com/prices')) {
                return Http::response(['data' => []], 200);
            }

            if ($url === 'https://sandbox-api.paddle.com/transactions') {
                return Http::response([
                    'data' => [
                        'id' => 'txn_created',
                        'status' => 'ready',
                        'checkout' => ['url' => 'https://pay.paddle.io/txn_created'],
                    ],
                ], 201);
            }

            return Http::response([], 404);
        });

        $tenant = $this->createTenant('Paddle Empty Product Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'gateway' => 'paddle',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.session_id', 'txn_created');

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://sandbox-api.paddle.com/prices'
            && $request->method() === 'POST'
            && $request['product_id'] === 'pro_empty_123'
            && $request['unit_price']['amount'] === '1900'
            && $request['unit_price']['currency_code'] === 'USD');

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://sandbox-api.paddle.com/transactions'
            && $request['items'][0]['price_id'] === 'pri_created');
    }

    public function test_paddle_validation_errors_are_surfaced(): void
    {
        Http::fake([
            'sandbox-api.paddle.com/transactions' => Http::response([
                'error' => [
                    'detail' => 'Invalid request.',
                    'errors' => [
                        ['field' => 'items.0.price_id', 'message' => 'The price id is invalid.'],
                    ],
                ],
            ], 400),
        ]);

        $tenant = $this->createTenant('Paddle Error Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'gateway' => 'paddle',
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Paddle transaction failed: Invalid request. (The price id is invalid.)');
    }

    public function test_checkout_is_refused_when_the_price_id_is_missing(): void
    {
        config()->set('services.paddle.price_id', null);

        $tenant = $this->createTenant('Unconfigured Paddle Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'gateway' => 'paddle',
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_signed_webhook_settles_the_invoice_and_upgrades_the_tenant(): void
    {
        $tenant = $this->createTenant('Paddle Webhook Household');
        $invoice = $this->pendingInvoice($tenant);
        $payload = $this->completedTransactionPayload($invoice);

        $this->postWebhook($payload, $this->signature($payload))
            ->assertOk()
            ->assertJsonPath('handled', true);

        $settled = $invoice->fresh();
        $this->assertSame('paid', $settled->status);
        $this->assertNotNull($settled->paid_at);
        $this->assertSame(
            'family',
            Tenancy::bypass(fn (): ?string => $tenant->fresh()?->subscription_tier),
        );
    }

    public function test_webhook_with_an_invalid_signature_is_rejected(): void
    {
        $tenant = $this->createTenant('Paddle Forged Household');
        $invoice = $this->pendingInvoice($tenant);
        $payload = $this->completedTransactionPayload($invoice);

        $this->postWebhook($payload, 'ts='.time().';h1=deadbeef')
            ->assertStatus(400);

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_webhook_with_a_missing_signature_is_rejected(): void
    {
        $tenant = $this->createTenant('Paddle Unsigned Household');
        $invoice = $this->pendingInvoice($tenant);
        $payload = $this->completedTransactionPayload($invoice);

        $this->postWebhook($payload, null)->assertStatus(400);

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_a_transaction_that_is_not_completed_does_not_settle(): void
    {
        $tenant = $this->createTenant('Paddle Pending Household');
        $invoice = $this->pendingInvoice($tenant);

        $payload = json_encode([
            'event_id' => 'ntf_02',
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_02',
                'status' => 'billed',
                'custom_data' => ['invoice_id' => (string) $invoice->id],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->postWebhook($payload, $this->signature($payload))
            ->assertOk()
            ->assertJsonPath('handled', false);

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_status_mapping_reads_the_transaction_back(): void
    {
        Http::fake([
            'sandbox-api.paddle.com/transactions/txn_completed' => Http::response(['data' => ['id' => 'txn_completed', 'status' => 'completed']], 200),
            'sandbox-api.paddle.com/transactions/txn_billed' => Http::response(['data' => ['id' => 'txn_billed', 'status' => 'billed']], 200),
            'sandbox-api.paddle.com/transactions/txn_canceled' => Http::response(['data' => ['id' => 'txn_canceled', 'status' => 'canceled']], 200),
        ]);

        $gateway = app(PaymentGatewayManager::class)->gateway('paddle', 'sandbox');

        $this->assertSame('paid', $gateway->verifyTestPayment('txn_completed')['status']);
        $this->assertSame('pending', $gateway->verifyTestPayment('txn_billed')['status']);
        $this->assertSame('failed', $gateway->verifyTestPayment('txn_canceled')['status']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://sandbox-api.paddle.com/transactions/txn_completed'
            && in_array('Bearer '.self::SANDBOX_KEY, $request->header('Authorization'), true));
    }

    public function test_status_lookup_failure_is_reported_as_unknown(): void
    {
        Http::fake([
            'sandbox-api.paddle.com/transactions/txn_missing' => Http::response(['error' => ['detail' => 'Transaction not found.']], 404),
        ]);

        $result = app(PaymentGatewayManager::class)->gateway('paddle', 'sandbox')
            ->verifyTestPayment('txn_missing');

        $this->assertSame('unknown', $result['status']);
        $this->assertStringContainsString('Transaction not found.', $result['message']);
    }

    public function test_a_live_key_uses_the_live_host(): void
    {
        config()->set('services.paddle.api_key', self::LIVE_KEY);

        Http::fake([
            'api.paddle.com/transactions*' => Http::response(['data' => []], 200),
        ]);

        $gateway = app(PaymentGatewayManager::class)->gateway('paddle');

        $this->assertSame('live', $gateway->mode());

        $gateway->verifyTestPayment('txn_live');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.paddle.com/transactions/txn_live');
    }

    public function test_the_console_payload_masks_every_paddle_secret(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/paddle', [
                'enabled' => true,
                'sandbox' => true,
                'credentials' => [
                    'sandbox' => [
                        'api_key' => self::SANDBOX_KEY,
                        'webhook_secret' => 'pdl_ntfset_stored',
                        'price_id' => 'pri_stored_family',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.credentials.sandbox.api_key', GatewayCredentials::MASK)
            ->assertJsonPath('data.credentials.sandbox.webhook_secret', GatewayCredentials::MASK);

        $response = $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment')
            ->assertOk()
            ->assertJsonPath('data.gateways.paddle.credentials.sandbox.api_key', GatewayCredentials::MASK)
            ->assertJsonPath('data.gateways.paddle.credentials.sandbox.webhook_secret', GatewayCredentials::MASK)
            // A price id is a catalogue reference, not a credential: it stays readable.
            ->assertJsonPath('data.gateways.paddle.credentials.sandbox.price_id', 'pri_stored_family');

        $this->assertStringNotContainsString(self::SANDBOX_KEY, $response->getContent());
        $this->assertStringNotContainsString('pdl_ntfset_stored', $response->getContent());
    }

    public function test_saving_a_public_key_id_only_is_rejected(): void
    {
        $platform = $this->createPlatformAdmin();

        $response = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/paddle', [
                'enabled' => true,
                'sandbox' => true,
                'credentials' => [
                    'sandbox' => [
                        // This is only Paddle's public key ID, not the 69-char key.
                        'api_key' => 'apikey_01m7cpzgv8cbh3sy76znstejzd',
                        'webhook_secret' => 'pdl_ntfset_test',
                        'price_id' => 'pri_test',
                    ],
                ],
            ])
            ->assertStatus(422);

        $this->assertStringContainsString('public key ID only', (string) $response->json('message'));
    }

    public function test_platform_gateway_test_calls_the_paddle_transactions_api(): void
    {
        Http::fake([
            'sandbox-api.paddle.com/transactions*' => Http::response(['data' => []], 200),
        ]);

        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'paddle'])
            ->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('mode', 'sandbox')
            ->assertJsonPath('message', 'Paddle API reachable with the configured sandbox API key.');

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://sandbox-api.paddle.com/transactions')
            && str_contains($request->url(), 'per_page=1')
            && in_array('Bearer '.self::SANDBOX_KEY, $request->header('Authorization'), true));
    }

    public function test_a_suffix_only_api_key_is_completed_with_the_mode_prefix(): void
    {
        config()->set('services.paddle.api_key', substr(self::SANDBOX_KEY, strlen('pdl_sdbx_')));

        Http::fake([
            'sandbox-api.paddle.com/transactions' => Http::response([
                'data' => [
                    'id' => 'txn_suffix',
                    'status' => 'ready',
                    'checkout' => ['url' => 'https://pay.paddle.io/txn_suffix'],
                ],
            ], 201),
        ]);

        $tenant = $this->createTenant('Paddle Suffix Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'gateway' => 'paddle',
            ])
            ->assertCreated();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://sandbox-api.paddle.com/transactions'
            && in_array('Bearer '.self::SANDBOX_KEY, $request->header('Authorization'), true));
    }

    public function test_a_truncated_api_key_is_reported_as_incomplete(): void
    {
        config()->set('services.paddle.api_key', 'pdl_sdbx_apikey_short');

        Http::fake();

        $platform = $this->createPlatformAdmin();

        $response = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'paddle', 'mode' => 'sandbox'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'unconfigured')
            ->assertJsonPath('gateway', 'paddle');

        $this->assertStringContainsString('incomplete', $response->json('message'));
        Http::assertNothingSent();
    }

    public function test_a_transaction_refused_for_a_missing_default_link_sets_it_and_retries_once(): void
    {
        $platform = $this->createPlatformAdmin();

        config()->set('services.paddle.api_key', null);
        config()->set('services.paddle.webhook_secret', null);
        config()->set('services.paddle.price_id', null);
        config()->set('services.paddle.default_payment_link', null);

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'paddle' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => [
                            'api_key' => self::SANDBOX_KEY,
                            'webhook_secret' => 'pdl_ntfset_sandbox',
                            'price_id' => 'pri_sandbox_family',
                            'default_payment_link' => 'https://console.example.test/paddle-pay',
                        ],
                    ],
                ],
            ],
        ]);

        $posts = 0;

        Http::fake(function (HttpRequest $request) use (&$posts) {
            $url = $request->url();
            $method = $request->method();

            if ($method === 'PATCH' && $url === 'https://sandbox-api.paddle.com/settings/checkout-url') {
                return Http::response(['data' => ['default_url' => 'https://console.example.test/paddle-pay']], 200);
            }

            if ($method === 'POST' && $url === 'https://sandbox-api.paddle.com/transactions') {
                $posts++;

                if ($posts === 1) {
                    return Http::response([
                        'error' => [
                            'type' => 'request_error',
                            'code' => 'transaction_default_checkout_url_not_set',
                            'detail' => 'Cannot create a transaction or open a checkout as no default payment link has been set for this account. Set in the Paddle dashboard, then try again.',
                        ],
                    ], 400);
                }

                return Http::response([
                    'data' => [
                        'id' => 'txn_link_set',
                        'status' => 'ready',
                        'checkout' => ['url' => 'https://pay.paddle.io/txn_link_set'],
                    ],
                ], 201);
            }

            return Http::response([], 404);
        });

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/paddle/sandbox-payment')
            ->assertCreated()
            ->assertJsonPath('data.reference', 'txn_link_set')
            ->assertJsonPath('data.url', 'https://pay.paddle.io/txn_link_set');

        // The first POST was refused, the link was set, and exactly one retry ran.
        $this->assertSame(2, $posts);

        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'PATCH'
            && $request->url() === 'https://sandbox-api.paddle.com/settings/checkout-url'
            && in_array('Bearer '.self::SANDBOX_KEY, $request->header('Authorization'), true)
            && $request['default_url'] === 'https://console.example.test/paddle-pay');
    }

    public function test_a_missing_default_payment_link_is_reported_clearly(): void
    {
        config()->set('services.paddle.default_payment_link', null);

        Http::fake();

        try {
            app(PaymentGatewayManager::class)->gateway('paddle', 'sandbox')->ensureDefaultPaymentLink();
            $this->fail('A missing default payment link must not be sent to Paddle.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Paddle requires a default payment link', $exception->getMessage());
            $this->assertStringContainsString('Default Payment Link', $exception->getMessage());
            $this->assertStringContainsString('https', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_an_http_default_payment_link_is_reported_clearly(): void
    {
        config()->set('services.paddle.default_payment_link', 'http://localhost:8000/paddle-pay');

        Http::fake();

        try {
            app(PaymentGatewayManager::class)->gateway('paddle', 'sandbox')->ensureDefaultPaymentLink();
            $this->fail('An http default payment link must not be sent to Paddle.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('https://', $exception->getMessage());
            $this->assertStringContainsString('localhost', $exception->getMessage());
            // The overlay is why the link does not have to be a real page.
            $this->assertStringContainsString('overlay', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_a_default_payment_link_paddle_refuses_names_the_missing_permission(): void
    {
        config()->set('services.paddle.default_payment_link', 'https://console.example.test/paddle-pay');

        Http::fake([
            'sandbox-api.paddle.com/settings/checkout-url' => Http::response([
                'error' => ['detail' => 'You do not have permission to perform this action.'],
            ], 403),
        ]);

        try {
            app(PaymentGatewayManager::class)->gateway('paddle', 'sandbox')->ensureDefaultPaymentLink();
            $this->fail('A rejected PATCH must raise.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Paddle could not set the default payment link', $exception->getMessage());
            $this->assertStringContainsString('checkout_settings.write', $exception->getMessage());
        }
    }

    public function test_saving_an_http_default_payment_link_is_rejected(): void
    {
        $platform = $this->createPlatformAdmin();

        $response = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/paddle', [
                'enabled' => true,
                'sandbox' => true,
                'credentials' => [
                    'sandbox' => [
                        'api_key' => self::SANDBOX_KEY,
                        'webhook_secret' => 'pdl_ntfset_sandbox',
                        'price_id' => 'pri_sandbox_family',
                        'client_token' => 'test_client_token_saved',
                        'default_payment_link' => 'http://localhost:8000/paddle-pay',
                    ],
                ],
            ])
            ->assertStatus(422);

        $this->assertStringContainsString('https://', (string) $response->json('message'));
        $this->assertStringContainsString('localhost', (string) $response->json('message'));
        $this->assertArrayNotHasKey(
            'paddle',
            PlatformSetting::get('payment_gateway')['gateways'] ?? [],
        );

        // An https link saves, and is presented as the card's default.
        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/paddle', [
                'enabled' => true,
                'sandbox' => true,
                'credentials' => [
                    'sandbox' => [
                        'api_key' => self::SANDBOX_KEY,
                        'webhook_secret' => 'pdl_ntfset_sandbox',
                        'price_id' => 'pri_sandbox_family',
                        'client_token' => 'test_client_token_saved',
                        'default_payment_link' => 'https://console.example.test/paddle-pay',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.credentials.sandbox.default_payment_link', 'https://console.example.test/paddle-pay')
            ->assertJsonPath('data.credentials.sandbox.client_token', GatewayCredentials::MASK)
            ->assertJsonPath('data.configured.sandbox', true);
    }

    public function test_paddle_credentials_resolve_and_mask_the_client_token_and_default_link(): void
    {
        config()->set('services.paddle.api_key', null);
        config()->set('services.paddle.webhook_secret', null);
        config()->set('services.paddle.price_id', null);
        config()->set('services.paddle.client_token', null);
        config()->set('services.paddle.default_payment_link', null);

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'paddle' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => [
                            'api_key' => self::SANDBOX_KEY,
                            'webhook_secret' => 'pdl_ntfset_sandbox',
                            'price_id' => 'pri_sandbox_family',
                            'client_token' => 'test_client_token_stored',
                            'default_payment_link' => 'https://console.example.test/paddle-pay',
                        ],
                        'live' => [
                            'api_key' => self::LIVE_KEY,
                            'webhook_secret' => 'pdl_ntfset_live',
                            'price_id' => 'pri_live_family',
                            // No client token: a live card cannot open the overlay.
                        ],
                    ],
                ],
            ],
        ]);

        $credentials = app(GatewayCredentials::class);
        $sandbox = $credentials->resolve('paddle', 'sandbox');

        $this->assertSame('test_client_token_stored', $sandbox['client_token']);
        $this->assertSame('https://console.example.test/paddle-pay', $sandbox['default_payment_link']);

        // The client token is masked on reads; the link is not a credential.
        $this->assertContains('client_token', $credentials->secretFields('paddle'));
        $this->assertSame(
            GatewayCredentials::MASK,
            $credentials->mask(['client_token' => 'test_client_token_stored'])['client_token'],
        );
        $this->assertSame(
            'https://console.example.test/paddle-pay',
            $credentials->mask(['default_payment_link' => 'https://console.example.test/paddle-pay'])['default_payment_link'],
        );

        // A full key and price id are no longer enough: the overlay needs a token.
        $this->assertTrue($credentials->isConfigured('paddle', 'sandbox'));
        $this->assertFalse($credentials->isConfigured('paddle', 'live'));

        $platform = $this->createPlatformAdmin();

        $response = $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment')
            ->assertOk()
            ->assertJsonPath('data.gateways.paddle.credentials.sandbox.client_token', GatewayCredentials::MASK)
            ->assertJsonPath('data.gateways.paddle.credentials.sandbox.default_payment_link', 'https://console.example.test/paddle-pay')
            ->assertJsonPath('data.gateways.paddle.configured.sandbox', true)
            ->assertJsonPath('data.gateways.paddle.configured.live', false);

        $this->assertStringNotContainsString('test_client_token_stored', $response->getContent());
    }

    public function test_the_paddle_driver_exposes_the_client_token_for_the_overlay(): void
    {
        $gateway = app(PaymentGatewayManager::class)->gateway('paddle', 'sandbox');

        $this->assertSame('test_client_token_fixture', $gateway->clientToken());

        config()->set('services.paddle.client_token', '   ');

        $this->assertNull(app(PaymentGatewayManager::class)->gateway('paddle', 'sandbox')->clientToken());
    }

    private function pendingInvoice(Tenant $tenant): Invoice
    {
        return Invoice::create([
            'tenant_id' => $tenant->id,
            'invoice_number' => Invoice::generateNumber(),
            'amount_cents' => 1900,
            'currency' => 'USD',
            'status' => 'pending',
            'tier' => 'family',
            'billing_period' => 'monthly',
            'payment_method' => 'paddle',
        ]);
    }

    private function completedTransactionPayload(Invoice $invoice): string
    {
        return json_encode([
            'event_id' => 'ntf_01',
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_01',
                'status' => 'completed',
                'custom_data' => [
                    'invoice_id' => (string) $invoice->id,
                    'tenant_id' => (string) $invoice->tenant_id,
                    'tier' => 'family',
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function signature(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.':'.$payload, 'pdl_ntfset_test_secret');

        return "ts={$timestamp};h1={$signature}";
    }

    private function postWebhook(string $payload, ?string $signature)
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($signature !== null) {
            $headers['HTTP_PADDLE_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/api/v1/webhooks/payments/paddle', [], [], [], $headers, $payload);
    }
}
