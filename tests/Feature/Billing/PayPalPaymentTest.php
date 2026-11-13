<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PayPalPaymentTest extends TestCase
{
    use CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paypal.client_id', 'paypal-client');
        config()->set('services.paypal.client_secret', 'paypal-secret');
        config()->set('services.paypal.webhook_id', 'WH-TEST-1');
        config()->set('services.paypal.mode', 'sandbox');
    }

    public function test_checkout_session_returns_the_paypal_approval_link(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token-1'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-1',
                'links' => [[
                    'rel' => 'approve',
                    'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1',
                ]],
            ], 201),
        ]);

        $tenant = $this->createTenant('PayPal Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'gateway' => 'paypal',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.session_id', 'ORDER-1')
            ->assertJsonPath('data.checkout.mode', 'sandbox')
            ->assertJsonPath(
                'data.checkout.url',
                'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1',
            );

        $invoiceId = $response->json('data.invoice.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'pending',
            'payment_method' => 'paypal',
        ]);

        Http::assertSent(function ($request) use ($invoiceId): bool {
            return $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
                && $request['purchase_units'][0]['custom_id'] === (string) $invoiceId
                && $request['purchase_units'][0]['amount']['value'] === '19.00';
        });
    }

    public function test_verified_approved_order_is_captured_and_settles_the_invoice(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token-1'], 200),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1/capture' => Http::response([
                'id' => 'CAPTURE-1',
                'status' => 'COMPLETED',
            ], 201),
        ]);

        $tenant = $this->createTenant('PayPal Webhook Household');
        $invoice = $this->pendingInvoice($tenant);

        $response = $this->postWebhook($this->approvedOrderPayload($invoice));

        $response->assertOk()->assertJsonPath('handled', true);

        $settled = $invoice->fresh();
        $this->assertSame('paid', $settled->status);
        $this->assertNotNull($settled->paid_at);
        $this->assertSame(
            'family',
            Tenancy::bypass(fn (): ?string => $tenant->fresh()?->subscription_tier),
        );

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1/capture');
    }

    public function test_failed_webhook_verification_is_rejected(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token-1'], 200),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'FAILURE',
            ], 200),
        ]);

        $tenant = $this->createTenant('PayPal Forged Household');
        $invoice = $this->pendingInvoice($tenant);

        $this->postWebhook($this->approvedOrderPayload($invoice))->assertStatus(400);

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_platform_gateway_test_obtains_a_paypal_token(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token-1'], 200),
        ]);

        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'paypal'])
            ->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('mode', 'sandbox');
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
            'payment_method' => 'paypal',
        ]);
    }

    private function approvedOrderPayload(Invoice $invoice): string
    {
        return json_encode([
            'id' => 'WH-EVT-1',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [
                'id' => 'ORDER-1',
                'custom_id' => (string) $invoice->id,
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function postWebhook(string $payload)
    {
        return $this->call('POST', '/api/v1/webhooks/payments/paypal', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.com/cert.pem',
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'transmission-1',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'signature-1',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(),
        ], $payload);
    }
}
