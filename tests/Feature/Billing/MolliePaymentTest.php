<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class MolliePaymentTest extends TestCase
{
    use CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.mollie.api_key', 'test_mollie_key');
        config()->set('services.mollie.webhook_url', 'https://example.test/api/v1/webhooks/payments/mollie');
    }

    public function test_checkout_returns_a_mollie_checkout_url(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments' => Http::response([
                'id' => 'tr_1',
                'status' => 'open',
                '_links' => [
                    'checkout' => ['href' => 'https://www.mollie.com/checkout/tr_1'],
                ],
            ], 201),
        ]);

        $tenant = $this->createTenant('Mollie Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'gateway' => 'mollie',
                'currency' => 'EUR',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.session_id', 'tr_1')
            ->assertJsonPath('data.checkout.mode', 'sandbox')
            ->assertJsonPath('data.checkout.url', 'https://www.mollie.com/checkout/tr_1');

        $invoiceId = $response->json('data.invoice.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'currency' => 'EUR',
            'status' => 'pending',
            'payment_method' => 'mollie',
        ]);

        Http::assertSent(function ($request) use ($invoiceId): bool {
            return $request->url() === 'https://api.mollie.com/v2/payments'
                && $request['amount']['value'] === '19.00'
                && $request['metadata']['invoice_id'] === (string) $invoiceId;
        });
    }

    public function test_paid_payment_is_settled_after_a_server_side_lookup(): void
    {
        $tenant = $this->createTenant('Mollie Webhook Household');
        $invoice = $this->pendingInvoice($tenant);

        Http::fake([
            'api.mollie.com/v2/payments/tr_1' => Http::response([
                'id' => 'tr_1',
                'status' => 'paid',
                'metadata' => ['invoice_id' => (string) $invoice->id],
            ], 200),
        ]);

        $this->post('/api/v1/webhooks/payments/mollie', ['id' => 'tr_1'])
            ->assertOk()
            ->assertJsonPath('handled', true);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(
            'family',
            Tenancy::bypass(fn (): ?string => $tenant->fresh()?->subscription_tier),
        );
    }

    public function test_unpaid_payment_is_rejected(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments/tr_2' => Http::response([
                'id' => 'tr_2',
                'status' => 'open',
            ], 200),
        ]);

        $tenant = $this->createTenant('Mollie Open Household');
        $invoice = $this->pendingInvoice($tenant);

        $this->post('/api/v1/webhooks/payments/mollie', ['id' => 'tr_2'])
            ->assertStatus(400);

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_platform_gateway_test_calls_the_mollie_payments_api(): void
    {
        Http::fake([
            'api.mollie.com/v2/payments*' => Http::response(['_embedded' => ['payments' => []]], 200),
        ]);

        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'mollie'])
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
            'currency' => 'EUR',
            'status' => 'pending',
            'tier' => 'family',
            'billing_period' => 'monthly',
            'payment_method' => 'mollie',
        ]);
    }
}
