<?php

namespace Tests\Feature\Platform;

use App\Models\AuditEvent;
use App\Models\PlatformSetting;
use App\Payments\GatewayCredentials;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The console edits payment gateway credentials per gateway and per mode, and
 * pings them. These tests pin the three things that make that safe: secrets are
 * masked on the way out, a mask sentinel never overwrites a stored secret, and
 * every mode resolves — and pings — only its own credentials.
 */
class PaymentGatewaySettingsTest extends TestCase
{
    use CreatesTenants;

    public function test_saving_one_gateway_persists_both_credential_sets_and_get_masks_every_secret(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/stripe', [
                'enabled' => true,
                'sandbox' => false,
                'credentials' => [
                    'sandbox' => [
                        'publishable_key' => 'pk_test_saved',
                        'secret_key' => 'sk_test_saved_secret',
                        'webhook_secret' => 'whsec_saved_sandbox',
                    ],
                    'live' => [
                        'publishable_key' => 'pk_live_saved',
                        'secret_key' => 'sk_live_saved_secret',
                        'webhook_secret' => 'whsec_saved_live',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.sandbox', false)
            ->assertJsonPath('data.credentials.sandbox.publishable_key', 'pk_test_saved')
            ->assertJsonPath('data.credentials.sandbox.secret_key', GatewayCredentials::MASK)
            ->assertJsonPath('data.credentials.live.secret_key', GatewayCredentials::MASK)
            ->assertJsonPath('data.credentials.live.webhook_secret', GatewayCredentials::MASK);

        $stored = PlatformSetting::get('payment_gateway');

        $this->assertSame('sk_test_saved_secret', $stored['gateways']['stripe']['credentials']['sandbox']['secret_key']);
        $this->assertSame('sk_live_saved_secret', $stored['gateways']['stripe']['credentials']['live']['secret_key']);

        $response = $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment')
            ->assertOk()
            ->assertJsonPath('data.active_gateway', 'stripe')
            ->assertJsonPath('data.gateways.stripe.sandbox', false)
            ->assertJsonPath('data.gateways.stripe.credentials.sandbox.secret_key', GatewayCredentials::MASK)
            ->assertJsonPath('data.gateways.stripe.credentials.sandbox.webhook_secret', GatewayCredentials::MASK)
            ->assertJsonPath('data.gateways.stripe.credentials.live.secret_key', GatewayCredentials::MASK)
            ->assertJsonPath('data.gateways.stripe.credentials.live.publishable_key', 'pk_live_saved')
            // The legacy flat fields stay for older clients, also masked.
            ->assertJsonPath('data.gateways.stripe.secret_key', GatewayCredentials::MASK)
            ->assertJsonStructure([
                'data' => [
                    'gateways' => [
                        'stripe' => [
                            'credentials' => ['sandbox', 'live'],
                            'last_test' => ['sandbox', 'live'],
                        ],
                    ],
                ],
            ]);

        foreach ([
            'sk_test_saved_secret',
            'sk_live_saved_secret',
            'whsec_saved_sandbox',
            'whsec_saved_live',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_resubmitting_the_mask_sentinel_keeps_the_stored_secret(): void
    {
        $platform = $this->createPlatformAdmin();

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'stripe' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => [
                            'publishable_key' => 'pk_test_original',
                            'secret_key' => 'sk_test_original_secret',
                            'webhook_secret' => 'whsec_original_sandbox',
                        ],
                        'live' => [
                            'publishable_key' => 'pk_live_original',
                            'secret_key' => 'sk_live_original_secret',
                            'webhook_secret' => 'whsec_original_live',
                        ],
                    ],
                ],
            ],
        ]);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/stripe', [
                'enabled' => true,
                'sandbox' => true,
                'credentials' => [
                    'sandbox' => [
                        'publishable_key' => 'pk_test_rotated',
                        'secret_key' => GatewayCredentials::MASK,
                        'webhook_secret' => '',
                    ],
                    'live' => [
                        'secret_key' => GatewayCredentials::MASK,
                    ],
                ],
            ])
            ->assertOk();

        $stored = PlatformSetting::get('payment_gateway')['gateways']['stripe']['credentials'];

        $this->assertSame('pk_test_rotated', $stored['sandbox']['publishable_key']);
        $this->assertSame('sk_test_original_secret', $stored['sandbox']['secret_key']);
        $this->assertSame('whsec_original_sandbox', $stored['sandbox']['webhook_secret']);
        $this->assertSame('sk_live_original_secret', $stored['live']['secret_key']);
        $this->assertSame('whsec_original_live', $stored['live']['webhook_secret']);
        foreach ($stored as $set) {
            $this->assertNotContains(GatewayCredentials::MASK, $set, true);
            $this->assertNotContains('', $set, true);
        }
    }

    public function test_global_save_without_gateways_keeps_the_saved_credentials(): void
    {
        $platform = $this->createPlatformAdmin();

        PlatformSetting::set('payment_gateway', [
            'active_gateway' => 'stripe',
            'mode' => 'sandbox',
            'currency' => 'USD',
            'auto_billing_enabled' => true,
            // Only Stripe is stored: a global save must keep that row instead of
            // replacing the whole `gateways` map with an empty one.
            'gateways' => [
                'stripe' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => ['secret_key' => 'sk_test_keep', 'webhook_secret' => 'whsec_keep'],
                        'live' => ['secret_key' => 'sk_live_keep', 'webhook_secret' => 'whsec_live_keep'],
                    ],
                ],
            ],
        ]);

        $response = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment', [
                'active_gateway' => 'stripe',
                'mode' => 'live',
                'currency' => 'EUR',
                'auto_billing_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.mode', 'live')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.gateways.stripe.credentials.sandbox.secret_key', GatewayCredentials::MASK);

        $stored = PlatformSetting::get('payment_gateway');

        $this->assertSame('EUR', $stored['currency']);
        $this->assertSame('sk_test_keep', $stored['gateways']['stripe']['credentials']['sandbox']['secret_key']);
        $this->assertSame('sk_live_keep', $stored['gateways']['stripe']['credentials']['live']['secret_key']);
        $this->assertStringNotContainsString('sk_test_keep', $response->getContent());
        $this->assertStringNotContainsString('sk_live_keep', $response->getContent());
    }

    public function test_sandbox_ping_uses_sandbox_credentials_and_live_ping_uses_live_credentials(): void
    {
        $platform = $this->createPlatformAdmin();

        config()->set('services.stripe.secret', 'sk_test_from_env');
        config()->set('services.stripe.webhook_secret', 'whsec_from_env');

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'stripe' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => ['secret_key' => 'sk_test_db_secret', 'webhook_secret' => 'whsec_test_db'],
                        'live' => ['secret_key' => 'sk_live_db_secret', 'webhook_secret' => 'whsec_live_db'],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'api.stripe.com/v1/balance' => Http::response(['object' => 'balance'], 200),
        ]);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'stripe', 'mode' => 'sandbox'])
            ->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('mode', 'sandbox');

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'stripe', 'mode' => 'live'])
            ->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('mode', 'live');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.stripe.com/v1/balance'
            && in_array('Bearer sk_test_db_secret', $request->header('Authorization'), true));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.stripe.com/v1/balance'
            && in_array('Bearer sk_live_db_secret', $request->header('Authorization'), true));

        Http::assertNotSent(fn ($request): bool => in_array('Bearer sk_test_from_env', $request->header('Authorization'), true));
    }

    public function test_a_ping_only_requires_the_fields_its_probe_uses(): void
    {
        $platform = $this->createPlatformAdmin();

        // The webhook secret is absent: a balance probe never uses it, so the
        // ping must still go out instead of reporting the gateway unconfigured.
        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'stripe' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => ['secret_key' => 'sk_test_probe_only'],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'api.stripe.com/v1/balance' => Http::response(['object' => 'balance'], 200),
        ]);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'stripe', 'mode' => 'sandbox'])
            ->assertOk()
            ->assertJsonPath('status', 'connected');

        Http::assertSent(fn ($request): bool => in_array(
            'Bearer sk_test_probe_only',
            $request->header('Authorization'),
            true,
        ));

        // Checkout still insists on the webhook secret, which is what webhooks use.
        $this->assertFalse(app(GatewayCredentials::class)->isConfigured('stripe', 'sandbox'));
    }

    public function test_a_ping_records_last_test_per_mode_and_audits_every_outcome(): void
    {
        $platform = $this->createPlatformAdmin();

        Http::fake([
            'api.stripe.com/v1/balance' => Http::response(['object' => 'balance'], 200),
        ]);

        PlatformSetting::set('payment_gateway', [
            'gateways' => [
                'stripe' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => ['secret_key' => 'sk_test_db_secret', 'webhook_secret' => 'whsec_test_db'],
                        'live' => ['secret_key' => '', 'webhook_secret' => ''],
                    ],
                ],
            ],
        ]);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'stripe', 'mode' => 'sandbox'])
            ->assertOk()
            ->assertJsonPath('status', 'connected');

        // The live set is empty and the environment is sandbox, so a live ping can
        // only be honestly reported as unconfigured — no credential fallback.
        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'stripe', 'mode' => 'live'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'unconfigured')
            ->assertJsonPath('mode', 'live');

        // A gateway the platform cannot ping never fabricates a success either.
        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'nagad', 'mode' => 'live'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'unconfigured')
            ->assertJsonPath('gateway', 'nagad');

        $gateway = PlatformSetting::get('payment_gateway')['gateways']['stripe'];
        $this->assertSame('connected', $gateway['last_test']['sandbox']['status']);
        $this->assertSame('unconfigured', $gateway['last_test']['live']['status']);
        $this->assertIsInt($gateway['last_test']['sandbox']['latency_ms']);
        $this->assertArrayHasKey('at', $gateway['last_test']['sandbox']);

        $audits = AuditEvent::where('action', 'platform.payment_gateway_tested')->latest('id')->get();
        $this->assertCount(3, $audits);

        $stripeSandbox = $audits->first(fn (AuditEvent $audit): bool => ($audit->payload['gateway'] ?? null) === 'stripe'
            && ($audit->payload['mode'] ?? null) === 'sandbox');
        $this->assertNotNull($stripeSandbox);
        $this->assertSame('connected', $stripeSandbox->payload['status']);
        $this->assertIsInt($stripeSandbox->payload['latency_ms']);

        $stripeLive = $audits->first(fn (AuditEvent $audit): bool => ($audit->payload['gateway'] ?? null) === 'stripe'
            && ($audit->payload['mode'] ?? null) === 'live');
        $this->assertNotNull($stripeLive);
        $this->assertSame('unconfigured', $stripeLive->payload['status']);

        foreach ($audits as $audit) {
            $this->assertStringNotContainsString('sk_test_db_secret', json_encode($audit->payload, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('whsec_test_db', json_encode($audit->payload, JSON_THROW_ON_ERROR));
        }
    }

    public function test_checkout_uses_the_stored_credentials_for_the_client_mode(): void
    {
        config()->set('services.stripe.secret', 'sk_test_from_env');
        config()->set('services.stripe.webhook_secret', 'whsec_from_env');

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_1',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_1',
            ], 200),
        ]);

        $tenant = $this->createTenant('Credentials Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        PlatformSetting::set('payment_gateway', [
            'currency' => 'USD',
            'gateways' => [
                'stripe' => [
                    'enabled' => true,
                    'sandbox' => true,
                    'credentials' => [
                        'sandbox' => ['secret_key' => 'sk_test_db_secret', 'webhook_secret' => 'whsec_test_db'],
                        'live' => ['secret_key' => 'sk_live_db_secret', 'webhook_secret' => 'whsec_live_db'],
                    ],
                ],
            ],
        ]);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'gateway' => 'stripe',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.session_id', 'cs_test_1')
            ->assertJsonPath('data.checkout.mode', 'sandbox');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && in_array('Bearer sk_test_db_secret', $request->header('Authorization'), true));

        // Flip the client mode to live: the live set is used, never the sandbox one.
        PlatformSetting::set('payment_gateway', [
            'currency' => 'USD',
            'gateways' => [
                'stripe' => [
                    'enabled' => true,
                    'sandbox' => false,
                    'credentials' => [
                        'sandbox' => ['secret_key' => 'sk_test_db_secret', 'webhook_secret' => 'whsec_test_db'],
                        'live' => ['secret_key' => 'sk_live_db_secret', 'webhook_secret' => 'whsec_live_db'],
                    ],
                ],
            ],
        ]);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'gateway' => 'stripe',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.mode', 'live');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && in_array('Bearer sk_live_db_secret', $request->header('Authorization'), true));
    }

    public function test_the_offline_gateway_cards_save_through_the_same_endpoint(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/cash', [
                'enabled' => true,
                'instructions' => 'Hand cash to the receptionist.',
                'auto_mark_paid' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.instructions', 'Hand cash to the receptionist.')
            ->assertJsonPath('data.auto_mark_paid', false);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/bank_transfer', [
                'enabled' => true,
                'bank_name' => 'City Bank',
                'account_number' => '0123456789',
            ])
            ->assertOk()
            ->assertJsonPath('data.bank_name', 'City Bank')
            ->assertJsonPath('data.account_number', GatewayCredentials::MASK);

        // The account number is a secret field: the sentinel keeps the stored one.
        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/bank_transfer', [
                'account_number' => GatewayCredentials::MASK,
                'swift_bic' => 'CHASUS33',
            ])
            ->assertOk();

        $stored = PlatformSetting::get('payment_gateway')['gateways']['bank_transfer'];
        $this->assertSame('0123456789', $stored['account_number']);
        $this->assertSame('CHASUS33', $stored['swift_bic']);

        // An unknown gateway is a 404 rather than a silent no-op.
        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/not-a-gateway', ['enabled' => true])
            ->assertNotFound();
    }
}
