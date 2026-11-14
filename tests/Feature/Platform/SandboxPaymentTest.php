<?php

namespace Tests\Feature\Platform;

use App\Models\AuditEvent;
use App\Models\PlatformSetting;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The console can run a real sandbox payment per gateway: it opens the provider's
 * hosted checkout and polls until the provider reports the outcome.
 *
 * These tests pin the three things that make that safe: the sandbox credential
 * set is used whatever the console's client-facing mode is (a live key is never
 * touched), the test invoice is force-filled and never persisted, and no request
 * or audit row carries a secret.
 */
class SandboxPaymentTest extends TestCase
{
    use CreatesTenants;

    private const PADDLE_SANDBOX_KEY = 'pdl_sdbx_apikey_'.'aaaaaaaaaaaaaaaaaaaaaaaaaa_bbbbbbbbbbbbbbbbbbbbbb_ccc';

    private const PADDLE_LIVE_KEY = 'pdl_live_apikey_'.'aaaaaaaaaaaaaaaaaaaaaaaaaa_bbbbbbbbbbbbbbbbbbbbbb_ccc';

    public function test_stripe_sandbox_payment_starts_a_checkout_with_the_sandbox_secret(): void
    {
        $platform = $this->createPlatformAdmin();

        config()->set('app.frontend_url', 'https://console.example.test');
        config()->set('services.stripe.secret', null);
        config()->set('services.stripe.webhook_secret', null);

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'stripe' => [
                    'enabled' => true,
                    // The console is pointed at live. The sandbox flow must still
                    // resolve the sandbox set.
                    'sandbox' => false,
                    'credentials' => [
                        'sandbox' => [
                            'secret_key' => 'sk_test_sandbox_secret',
                            'webhook_secret' => 'whsec_sandbox_secret',
                        ],
                        'live' => [
                            'secret_key' => 'sk_live_live_secret',
                            'webhook_secret' => 'whsec_live_secret',
                        ],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_sandbox_1',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_sandbox_1',
            ], 200),
        ]);

        $invoicesBefore = $this->invoiceCount();

        $response = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/stripe/sandbox-payment')
            ->assertCreated()
            ->assertJsonPath('data.gateway', 'stripe')
            ->assertJsonPath('data.mode', 'sandbox')
            ->assertJsonPath('data.reference', 'cs_test_sandbox_1')
            ->assertJsonPath('data.url', 'https://checkout.stripe.com/c/pay/cs_test_sandbox_1')
            ->assertJsonPath('data.amount_cents', 100)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.simulated', false);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && in_array('Bearer sk_test_sandbox_secret', $request->header('Authorization'), true)
            && $request['line_items'][0]['price_data']['unit_amount'] === 100
            && $request['line_items'][0]['price_data']['currency'] === 'usd'
            && str_starts_with(
                $request['success_url'],
                'https://console.example.test/sandbox/payment-result/',
            )
            && str_contains($request['success_url'], 'gateway=stripe')
            && str_ends_with($request['success_url'], '&flow=success'));

        Http::assertNotSent(fn (HttpRequest $request): bool => in_array(
            'Bearer sk_live_live_secret',
            $request->header('Authorization'),
            true,
        ));

        // The transient invoice must never reach the billing ledger.
        $this->assertSame($invoicesBefore, $this->invoiceCount());

        $audit = AuditEvent::where('action', 'platform.payment_gateway_sandbox_payment_started')->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertSame('stripe', $audit->payload['gateway']);
        $this->assertSame('sandbox', $audit->payload['mode']);
        $this->assertSame('cs_test_sandbox_1', $audit->payload['reference']);
        $this->assertSame(100, $audit->payload['amount_cents']);
        $this->assertSame('USD', $audit->payload['currency']);

        $payload = json_encode($audit->payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('sk_test_sandbox_secret', $payload);
        $this->assertStringNotContainsString('whsec_sandbox_secret', $payload);
        $this->assertStringNotContainsString('sk_test_sandbox_secret', $response->getContent());
    }

    public function test_a_sandbox_payment_returns_a_transient_invoice_that_settles_with_the_payment(): void
    {
        $platform = $this->createPlatformAdmin();

        config()->set('services.stripe.secret', null);
        config()->set('services.stripe.webhook_secret', null);

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'stripe' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => [
                            'secret_key' => 'sk_test_invoice',
                            'webhook_secret' => 'whsec_invoice',
                        ],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_invoice',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_invoice',
            ], 200),
            'api.stripe.com/v1/checkout/sessions/cs_test_invoice' => Http::response([
                'id' => 'cs_test_invoice',
                'payment_status' => 'paid',
                'status' => 'complete',
            ], 200),
        ]);

        $invoicesBefore = $this->invoiceCount();

        $started = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/stripe/sandbox-payment')
            ->assertCreated()
            ->assertJsonPath('data.invoice.status', 'pending')
            ->assertJsonPath('data.invoice.sandbox', true)
            ->assertJsonPath('data.invoice.amount_cents', 100)
            ->assertJsonPath('data.invoice.currency', 'USD')
            ->assertJsonPath('data.invoice.payment_method', 'stripe');

        $invoiceNumber = (string) $started->json('data.invoice.invoice_number');
        $this->assertStringStartsWith('TEST-STRIPE-', $invoiceNumber);

        $paid = $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/stripe/sandbox-payment/cs_test_invoice')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.invoice.status', 'paid')
            ->assertJsonPath('data.invoice.invoice_number', $invoiceNumber);

        $paidAt = (string) $paid->json('data.invoice.paid_at');
        $this->assertNotSame('', $paidAt);

        // Repeated polls show the same settlement time,
        // and the invoice never reaches the billing ledger.
        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/stripe/sandbox-payment/cs_test_invoice')
            ->assertOk()
            ->assertJsonPath('data.invoice.paid_at', $paidAt);

        $this->assertSame($invoicesBefore, $this->invoiceCount());
    }

    public function test_the_result_page_token_returns_the_settled_invoice(): void
    {
        $platform = $this->createPlatformAdmin();

        config()->set('app.frontend_url', 'https://console.example.test');

        Http::fake();

        $started = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/mock/sandbox-payment')
            ->assertCreated();

        $resultUrl = (string) $started->json('data.result_url');
        $this->assertStringStartsWith('https://console.example.test/sandbox/payment-result/', $resultUrl);

        $token = basename((string) parse_url($resultUrl, PHP_URL_PATH));
        $this->assertNotSame('', $token);

        // The result page is public: the unguessable token is the capability.
        $this->getJson('/api/v1/sandbox-payment-result/'.$token)
            ->assertOk()
            ->assertJsonPath('data.gateway', 'mock')
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.invoice.status', 'paid');

        // A token that was never issued is a 404, not an empty invoice.
        $this->getJson('/api/v1/sandbox-payment-result/not-a-token')
            ->assertNotFound();
    }

    public function test_sandbox_status_maps_stripe_paid_pending_failed_and_unknown(): void
    {
        $platform = $this->createPlatformAdmin();

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'stripe' => [
                    'enabled' => true,
                    'credentials' => [
                        'sandbox' => ['secret_key' => 'sk_test_sandbox_secret'],
                    ],
                ],
            ],
        ]);

        Http::fake(function (HttpRequest $request) {
            return match (true) {
                str_ends_with($request->url(), '/cs_test_paid') => Http::response(
                    ['id' => 'cs_test_paid', 'payment_status' => 'paid', 'status' => 'complete'],
                    200,
                ),
                str_ends_with($request->url(), '/cs_test_open') => Http::response(
                    ['id' => 'cs_test_open', 'payment_status' => 'unpaid', 'status' => 'open'],
                    200,
                ),
                str_ends_with($request->url(), '/cs_test_expired') => Http::response(
                    ['id' => 'cs_test_expired', 'payment_status' => 'unpaid', 'status' => 'expired'],
                    200,
                ),
                default => Http::response(['error' => ['message' => 'No such checkout session.']], 404),
            };
        });

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/stripe/sandbox-payment/cs_test_paid')
            ->assertOk()
            ->assertJsonPath('data.gateway', 'stripe')
            ->assertJsonPath('data.mode', 'sandbox')
            ->assertJsonPath('data.reference', 'cs_test_paid')
            ->assertJsonPath('data.status', 'paid');

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/stripe/sandbox-payment/cs_test_open')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/stripe/sandbox-payment/cs_test_expired')
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        // A provider that cannot be asked is reported, never raised.
        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/stripe/sandbox-payment/cs_test_missing')
            ->assertOk()
            ->assertJsonPath('data.status', 'unknown')
            ->assertJsonPath('data.message', 'Stripe rejected the status lookup: No such checkout session.');

        // Polling settles nothing and writes no audit row.
        $this->assertSame(0, AuditEvent::where('action', 'platform.payment_gateway_sandbox_payment_started')->count());
    }

    public function test_missing_sandbox_credentials_are_a_422_naming_the_field(): void
    {
        $platform = $this->createPlatformAdmin();

        config()->set('services.mollie.api_key', null);

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'mollie' => [
                    'enabled' => true,
                    'credentials' => [
                        'sandbox' => [],
                        'live' => ['api_key' => 'live_abc123'],
                    ],
                ],
            ],
        ]);

        Http::fake();

        $response = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/mollie/sandbox-payment')
            ->assertStatus(422)
            ->assertJsonPath('status', 'unconfigured');

        $this->assertStringContainsString('api_key', $response->json('message'));

        // Nothing was sent, and nothing was audited as started.
        Http::assertNothingSent();
        $this->assertSame(0, AuditEvent::where('action', 'platform.payment_gateway_sandbox_payment_started')->count());
    }

    public function test_the_mock_gateway_is_simulated_with_no_http_call_and_reports_paid(): void
    {
        $platform = $this->createPlatformAdmin();

        Http::fake();

        $started = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/mock/sandbox-payment')
            ->assertCreated()
            ->assertJsonPath('data.gateway', 'mock')
            ->assertJsonPath('data.mode', 'sandbox')
            ->assertJsonPath('data.url', null)
            ->assertJsonPath('data.amount_cents', 100)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.simulated', true)
            ->assertJsonPath('data.invoice.status', 'pending')
            ->assertJsonPath('data.invoice.sandbox', true);

        $reference = (string) $started->json('data.reference');

        $this->assertStringStartsWith('mock-', $reference);
        Http::assertNothingSent();

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/mock/sandbox-payment/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.gateway', 'mock')
            ->assertJsonPath('data.mode', 'sandbox')
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.invoice.status', 'paid');

        $audit = AuditEvent::where('action', 'platform.payment_gateway_sandbox_payment_started')->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertSame('mock', $audit->payload['gateway']);
        $this->assertTrue($audit->payload['simulated']);
        $this->assertSame($reference, $audit->payload['reference']);
    }

    public function test_a_gateway_without_a_hosted_checkout_is_a_404(): void
    {
        $platform = $this->createPlatformAdmin();

        Http::fake();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/cash/sandbox-payment')
            ->assertNotFound();

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/cash/sandbox-payment/cash-1')
            ->assertNotFound();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/nagad/sandbox-payment')
            ->assertNotFound();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/not-a-gateway/sandbox-payment')
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_sslcommerz_polls_by_tran_id_rather_than_by_session_key(): void
    {
        $platform = $this->createPlatformAdmin();

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'sslcommerz' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => ['store_id' => 'store_sandbox', 'store_passwd' => 'pass_sandbox'],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response([
                'status' => 'SUCCESS',
                'sessionkey' => 'SESSION-KEY-1',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/EasyCheckOut/test',
            ], 200),
            'sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response([
                'APIConnect' => 'DONE',
                'no_of_trans_found' => 1,
                // The real response nests the record under `element`.
                'element' => [['status' => 'VALIDATED', 'tran_id' => 'TEST-SSLCOMMERZ-1']],
            ], 200),
        ]);

        $started = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/sslcommerz/sandbox-payment')
            ->assertCreated()
            ->assertJsonPath('data.amount_cents', 1000)
            ->assertJsonPath('data.currency', 'BDT')
            ->assertJsonPath('data.url', 'https://sandbox.sslcommerz.com/EasyCheckOut/test');

        $reference = (string) $started->json('data.reference');

        // SSLCommerz looks a payment up by `tran_id`, not by the session key the
        // checkout page was created under.
        $this->assertNotSame('SESSION-KEY-1', $reference);
        $this->assertStringStartsWith('TEST-SSLCOMMERZ-', $reference);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
            && $request['tran_id'] === $reference);

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/sslcommerz/sandbox-payment/'.$reference)
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        Http::assertSent(fn (HttpRequest $request): bool => str_starts_with(
            $request->url(),
            'https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php',
        ) && $request['tran_id'] === $reference
            && $request['store_id'] === 'store_sandbox'
            && $request['format'] === 'json');
    }

    public function test_paypal_sandbox_status_reports_paid_pending_and_captures_an_approved_order(): void
    {
        $platform = $this->createPlatformAdmin();

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'paypal' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => ['client_id' => 'sb-client-id', 'client_secret' => 'sb-client-secret'],
                        'live' => ['client_id' => 'live-client-id', 'client_secret' => 'live-client-secret'],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'A21.sandbox.token'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-PAID' => Http::response(['id' => 'ORDER-PAID', 'status' => 'COMPLETED'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-APPROVED' => Http::response(['id' => 'ORDER-APPROVED', 'status' => 'APPROVED'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-APPROVED/capture' => Http::response(['id' => 'CAPTURE-1', 'status' => 'COMPLETED'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-VOIDED' => Http::response(['id' => 'ORDER-VOIDED', 'status' => 'VOIDED'], 200),
        ]);

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/paypal/sandbox-payment/ORDER-PAID')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        // An approved order is captured before it can be called paid.
        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/paypal/sandbox-payment/ORDER-APPROVED')
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-APPROVED/capture');

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/paypal/sandbox-payment/ORDER-VOIDED')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_razorpay_mollie_and_paystack_sandbox_status_match_with_the_sandbox_keys(): void
    {
        $platform = $this->createPlatformAdmin();

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'razorpay' => [
                    'enabled' => true,
                    'credentials' => [
                        'sandbox' => ['key_id' => 'rzp_test_sandbox', 'key_secret' => 'rzp_test_secret'],
                        'live' => ['key_id' => 'rzp_live_key', 'key_secret' => 'rzp_live_secret'],
                    ],
                ],
                'mollie' => [
                    'enabled' => true,
                    'credentials' => ['sandbox' => ['api_key' => 'test_sandbox_key']],
                ],
                'paystack' => [
                    'enabled' => true,
                    'credentials' => [
                        'sandbox' => ['secret_key' => 'sk_test_sandbox_key'],
                        'live' => ['secret_key' => 'sk_live_live_key'],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_paid' => Http::response(['id' => 'plink_paid', 'status' => 'paid'], 200),
            'api.razorpay.com/v1/payment_links/plink_expired' => Http::response(['id' => 'plink_expired', 'status' => 'expired'], 200),
            'api.mollie.com/v2/payments/tr_open' => Http::response(['id' => 'tr_open', 'status' => 'open'], 200),
            'api.mollie.com/v2/payments/tr_failed' => Http::response(['id' => 'tr_failed', 'status' => 'failed'], 200),
            'api.paystack.co/transaction/verify/ps_success' => Http::response(['status' => true, 'data' => ['status' => 'success']], 200),
            'api.paystack.co/transaction/verify/ps_pending' => Http::response(['status' => true, 'data' => ['status' => 'ongoing']], 200),
        ]);

        foreach ([
            'razorpay' => ['plink_paid' => 'paid', 'plink_expired' => 'failed'],
            'mollie' => ['tr_open' => 'pending', 'tr_failed' => 'failed'],
            'paystack' => ['ps_success' => 'paid', 'ps_pending' => 'pending'],
        ] as $gateway => $expectations) {
            foreach ($expectations as $reference => $status) {
                $this->withToken($this->tokenFor($platform))
                    ->getJson("/api/v1/platform/settings/payment/{$gateway}/sandbox-payment/{$reference}")
                    ->assertOk()
                    ->assertJsonPath('data.gateway', $gateway)
                    ->assertJsonPath('data.mode', 'sandbox')
                    ->assertJsonPath('data.status', $status);
            }
        }

        // The sandbox keys are what went out; the live set was never used.
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.razorpay.com/v1/payment_links/plink_paid'
            && in_array('Basic '.base64_encode('rzp_test_sandbox:rzp_test_secret'), $request->header('Authorization'), true));

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.paystack.co/transaction/verify/ps_success'
            && in_array('Bearer sk_test_sandbox_key', $request->header('Authorization'), true));

        Http::assertNotSent(fn (HttpRequest $request): bool => in_array('Bearer sk_live_live_key', $request->header('Authorization'), true)
            || in_array('Basic '.base64_encode('rzp_live_key:rzp_live_secret'), $request->header('Authorization'), true));
    }

    public function test_bkash_sandbox_status_grants_a_token_and_maps_the_transaction_status(): void
    {
        $platform = $this->createPlatformAdmin();

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'bkash' => [
                    'enabled' => true,
                    'credentials' => [
                        'sandbox' => [
                            'app_key' => 'sandbox_app_key',
                            'app_secret' => 'sandbox_app_secret',
                            'username' => 'sandbox_user',
                            'password' => 'sandbox_password',
                        ],
                    ],
                ],
            ],
        ]);

        Http::fake(function (HttpRequest $request) {
            if (str_contains($request->url(), 'token/grant')) {
                return Http::response(['id_token' => 'sandbox-id-token'], 200);
            }

            return Http::response(match ($request['paymentID'] ?? null) {
                'PAY-COMPLETED' => ['statusCode' => '0000', 'transactionStatus' => 'Completed'],
                'PAY-FAILED' => ['statusCode' => '0000', 'transactionStatus' => 'Failed'],
                default => ['statusCode' => '0000', 'transactionStatus' => 'Pending'],
            }, 200);
        });

        foreach (['PAY-COMPLETED' => 'paid', 'PAY-PENDING' => 'pending', 'PAY-FAILED' => 'failed'] as $reference => $status) {
            $this->withToken($this->tokenFor($platform))
                ->getJson("/api/v1/platform/settings/payment/bkash/sandbox-payment/{$reference}")
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/payment/status'
            && $request['paymentID'] === 'PAY-COMPLETED'
            && in_array('sandbox-id-token', $request->header('Authorization'), true));
    }

    public function test_paddle_sandbox_payment_starts_a_transaction_and_settles_it_as_paid(): void
    {
        $platform = $this->createPlatformAdmin();

        config()->set('app.frontend_url', 'https://console.example.test');
        config()->set('services.paddle.api_key', null);
        config()->set('services.paddle.webhook_secret', null);
        config()->set('services.paddle.price_id', null);

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'paddle' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => [
                            'api_key' => self::PADDLE_SANDBOX_KEY,
                            'webhook_secret' => 'pdl_ntfset_sandbox',
                            'price_id' => 'pri_sandbox_family',
                        ],
                        'live' => [
                            'api_key' => self::PADDLE_LIVE_KEY,
                            'webhook_secret' => 'pdl_ntfset_live',
                            'price_id' => 'pri_live_family',
                        ],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'sandbox-api.paddle.com/transactions' => Http::response([
                'data' => [
                    'id' => 'txn_sandbox_1',
                    'status' => 'ready',
                    'checkout' => ['url' => 'https://pay.paddle.io/txn_sandbox_1'],
                ],
            ], 201),
            'sandbox-api.paddle.com/transactions/txn_sandbox_1' => Http::response([
                'data' => ['id' => 'txn_sandbox_1', 'status' => 'completed'],
            ], 200),
        ]);

        $started = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/paddle/sandbox-payment')
            ->assertCreated()
            ->assertJsonPath('data.gateway', 'paddle')
            ->assertJsonPath('data.mode', 'sandbox')
            ->assertJsonPath('data.reference', 'txn_sandbox_1')
            ->assertJsonPath('data.url', 'https://pay.paddle.io/txn_sandbox_1')
            ->assertJsonPath('data.amount_cents', 100)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.simulated', false)
            ->assertJsonPath('data.invoice.status', 'pending')
            ->assertJsonPath('data.invoice.payment_method', 'paddle');

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://sandbox-api.paddle.com/transactions'
            && in_array('Bearer '.self::PADDLE_SANDBOX_KEY, $request->header('Authorization'), true)
            && $request['items'][0]['price_id'] === 'pri_sandbox_family'
            && $request['collection_mode'] === 'automatic');

        // The live set is never touched by a sandbox payment.
        Http::assertNotSent(fn (HttpRequest $request): bool => in_array(
            'Bearer '.self::PADDLE_LIVE_KEY,
            $request->header('Authorization'),
            true,
        ));

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment/paddle/sandbox-payment/txn_sandbox_1')
            ->assertOk()
            ->assertJsonPath('data.gateway', 'paddle')
            ->assertJsonPath('data.mode', 'sandbox')
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.invoice.status', 'paid');

        // The provider sends the browser back with a token in the path; that token
        // resolves the same display-only invoice without a session.
        $token = basename((string) parse_url((string) $started->json('data.result_url'), PHP_URL_PATH));

        $this->getJson('/api/v1/sandbox-payment-result/'.$token)
            ->assertOk()
            ->assertJsonPath('data.gateway', 'paddle')
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.invoice.status', 'paid')
            ->assertJsonPath('data.invoice.payment_method', 'paddle');
    }

    /**
     * Paddle Checkout is opened as a Paddle.js overlay, not by following the
     * transaction URL, so the sandbox start response carries the client-side
     * token. It is public by design — the browser hands it to Paddle.js — so it
     * is returned as stored rather than masked.
     */
    public function test_a_paddle_sandbox_payment_returns_the_client_token_for_the_overlay(): void
    {
        $platform = $this->createPlatformAdmin();

        config()->set('services.paddle.api_key', null);
        config()->set('services.paddle.webhook_secret', null);
        config()->set('services.paddle.price_id', null);
        config()->set('services.paddle.client_token', null);

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'paddle' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => [
                            'api_key' => self::PADDLE_SANDBOX_KEY,
                            'webhook_secret' => 'pdl_ntfset_sandbox',
                            'price_id' => 'pri_sandbox_family',
                            'client_token' => 'test_client_token_overlay',
                        ],
                        'live' => [
                            'client_token' => 'live_client_token_overlay',
                        ],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'sandbox-api.paddle.com/transactions' => Http::response([
                'data' => [
                    'id' => 'txn_overlay',
                    'status' => 'ready',
                    'checkout' => ['url' => 'https://pay.paddle.io/txn_overlay'],
                ],
            ], 201),
        ]);

        $response = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/paddle/sandbox-payment')
            ->assertCreated()
            ->assertJsonPath('data.gateway', 'paddle')
            ->assertJsonPath('data.reference', 'txn_overlay')
            ->assertJsonPath('data.client_token', 'test_client_token_overlay');

        // The sandbox token is what goes out; the live one is never exposed here.
        $this->assertStringNotContainsString('live_client_token_overlay', $response->getContent());

        // The client token is not a secret in the audit payload either way, but
        // it must not appear in it at all.
        $audit = AuditEvent::where('action', 'platform.payment_gateway_sandbox_payment_started')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertArrayNotHasKey('client_token', $audit->payload);
    }

    public function test_only_paddle_sandbox_responses_carry_a_client_token(): void
    {
        $platform = $this->createPlatformAdmin();

        config()->set('services.stripe.secret', null);
        config()->set('services.stripe.webhook_secret', null);

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'stripe' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => ['secret_key' => 'sk_test_tokenless', 'webhook_secret' => 'whsec_tokenless'],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_tokenless',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_tokenless',
            ], 200),
        ]);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/stripe/sandbox-payment')
            ->assertCreated()
            ->assertJsonMissingPath('data.client_token');

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/mock/sandbox-payment')
            ->assertCreated()
            ->assertJsonMissingPath('data.client_token');
    }

    public function test_a_paddle_sandbox_payment_prefers_the_test_price_id(): void
    {
        $platform = $this->createPlatformAdmin();

        config()->set('services.paddle.api_key', null);
        config()->set('services.paddle.webhook_secret', null);
        config()->set('services.paddle.price_id', null);
        config()->set('services.paddle.test_price_id', null);

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'paddle' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => [
                            'api_key' => self::PADDLE_SANDBOX_KEY,
                            'webhook_secret' => 'pdl_ntfset_sandbox',
                            'price_id' => 'pri_plan',
                            'test_price_id' => 'pri_test_oneoff',
                        ],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'sandbox-api.paddle.com/transactions' => Http::response([
                'data' => [
                    'id' => 'txn_test_price',
                    'status' => 'ready',
                    'checkout' => ['url' => 'https://pay.paddle.io/txn_test_price'],
                ],
            ], 201),
        ]);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/paddle/sandbox-payment')
            ->assertCreated()
            ->assertJsonPath('data.reference', 'txn_test_price');

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://sandbox-api.paddle.com/transactions'
            && $request['items'][0]['price_id'] === 'pri_test_oneoff');
    }

    /** Read the invoice table as the owner, so RLS cannot hide a stray row. */
    private function invoiceCount(): int
    {
        return DB::connection('pgsql_admin')->table('invoices')->count();
    }
}
