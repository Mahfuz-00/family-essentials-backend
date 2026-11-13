<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class SslCommerzPaymentTest extends TestCase
{
    use CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sslcommerz.store_id', 'fairshare-sandbox');
        config()->set('services.sslcommerz.store_password', 'sandbox-pass');
        config()->set('services.sslcommerz.mode', 'sandbox');
    }

    public function test_checkout_creates_an_sslcommerz_session(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response([
                'status' => 'SUCCESS',
                'sessionkey' => 'SESSION-1',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/EasyCheckOut/test',
            ], 200),
        ]);

        $tenant = $this->createTenant('SSLCommerz Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'gateway' => 'sslcommerz',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.session_id', 'SESSION-1')
            ->assertJsonPath('data.checkout.mode', 'sandbox')
            ->assertJsonPath(
                'data.checkout.url',
                'https://sandbox.sslcommerz.com/EasyCheckOut/test',
            );

        $invoiceId = $response->json('data.invoice.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'pending',
            'payment_method' => 'sslcommerz',
        ]);

        Http::assertSent(function ($request) use ($invoiceId): bool {
            return $request->url() === 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
                && $request['value_a'] === (string) $invoiceId
                && $request['total_amount'] === '19.00';
        });
    }

    public function test_validated_ipn_settles_the_invoice(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response([
                'status' => 'VALID',
                'tran_id' => 'INV-TEST-1',
                'bank_tran_id' => 'BANK-1',
            ], 200),
        ]);

        $tenant = $this->createTenant('SSLCommerz Webhook Household');
        $invoice = $this->pendingInvoice($tenant);

        $this->post('/api/v1/webhooks/payments/sslcommerz', [
            'val_id' => 'VAL-1',
            'value_a' => (string) $invoice->id,
            'status' => 'VALID',
        ])->assertOk()->assertJsonPath('handled', true);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(
            'family',
            Tenancy::bypass(fn (): ?string => $tenant->fresh()?->subscription_tier),
        );
    }

    public function test_invalid_ipn_is_rejected(): void
    {
        Http::fake([
            'sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response([
                'status' => 'INVALID',
            ], 200),
        ]);

        $tenant = $this->createTenant('SSLCommerz Forged Household');
        $invoice = $this->pendingInvoice($tenant);

        $this->post('/api/v1/webhooks/payments/sslcommerz', [
            'val_id' => 'VAL-2',
            'value_a' => (string) $invoice->id,
        ])->assertStatus(400);

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_platform_gateway_test_reports_the_configured_store(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'sslcommerz'])
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
            'payment_method' => 'sslcommerz',
        ]);
    }
}
