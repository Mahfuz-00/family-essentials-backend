<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class StripePaymentTest extends TestCase
{
    use CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.stripe.secret', 'sk_test_fairshare');
        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');
    }

    public function test_checkout_session_is_created_for_a_plan_change(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            ], 200),
        ]);

        $tenant = $this->createTenant('Stripe Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'gateway' => 'stripe',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.session_id', 'cs_test_123')
            ->assertJsonPath('data.checkout.mode', 'sandbox')
            ->assertJsonPath('data.checkout.url', 'https://checkout.stripe.com/c/pay/cs_test_123');

        $invoiceId = $response->json('data.invoice.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'pending',
            'tier' => 'family',
            'amount_cents' => 1900,
        ]);

        Http::assertSent(function ($request) use ($invoiceId): bool {
            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && $request['client_reference_id'] === (string) $invoiceId
                && $request['line_items'][0]['price_data']['unit_amount'] === 1900
                && $request['metadata']['invoice_id'] === (string) $invoiceId;
        });
    }

    public function test_checkout_is_refused_when_stripe_is_not_configured(): void
    {
        config()->set('services.stripe.secret', null);

        $tenant = $this->createTenant('Unconfigured Stripe Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'gateway' => 'stripe',
            ])
            ->assertStatus(422);
    }

    public function test_signed_webhook_settles_the_invoice_and_upgrades_the_tenant(): void
    {
        $tenant = $this->createTenant('Stripe Webhook Household');
        $invoice = $this->pendingInvoice($tenant);

        $payload = $this->completedSessionPayload($invoice);

        $response = $this->postWebhook($payload, $this->signature($payload));

        $response->assertOk()->assertJsonPath('handled', true);

        $settled = $invoice->fresh();
        $this->assertSame('paid', $settled->status);
        $this->assertNotNull($settled->paid_at);
        $this->assertSame(
            'family',
            Tenancy::bypass(fn (): ?string => $tenant->fresh()?->subscription_tier),
        );
    }

    public function test_repeated_webhook_is_idempotent(): void
    {
        $tenant = $this->createTenant('Stripe Repeat Household');
        $invoice = $this->pendingInvoice($tenant);
        $payload = $this->completedSessionPayload($invoice);

        $this->postWebhook($payload, $this->signature($payload))->assertOk();
        $this->postWebhook($payload, $this->signature($payload))->assertOk();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, Invoice::where('tenant_id', $tenant->id)->count());
    }

    public function test_webhook_with_an_invalid_signature_is_rejected(): void
    {
        $tenant = $this->createTenant('Stripe Forged Household');
        $invoice = $this->pendingInvoice($tenant);
        $payload = $this->completedSessionPayload($invoice);

        $response = $this->postWebhook($payload, 't='.time().',v1=deadbeef');

        $response->assertStatus(400);
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_platform_gateway_test_calls_the_stripe_api(): void
    {
        Http::fake([
            'api.stripe.com/v1/balance' => Http::response(['object' => 'balance'], 200),
        ]);

        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'stripe'])
            ->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('mode', 'sandbox');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.stripe.com/v1/balance');
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
            'payment_method' => 'stripe',
        ]);
    }

    private function completedSessionPayload(Invoice $invoice): string
    {
        return json_encode([
            'id' => 'evt_test_1',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'client_reference_id' => (string) $invoice->id,
                    'payment_status' => 'paid',
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function signature(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

        return "t={$timestamp},v1={$signature}";
    }

    private function postWebhook(string $payload, string $signature)
    {
        return $this->call('POST', '/api/v1/webhooks/payments/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);
    }
}
