<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class BkashPaymentTest extends TestCase
{
    use CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.bkash.app_key', 'bkash-app');
        config()->set('services.bkash.app_secret', 'bkash-secret');
        config()->set('services.bkash.username', 'bkash-user');
        config()->set('services.bkash.password', 'bkash-password');
        config()->set('services.bkash.mode', 'sandbox');

        PlatformSetting::set('payment_gateway', [
            'currency' => 'BDT',
            'gateways' => [],
        ]);
    }

    public function test_checkout_creates_a_bkash_payment(): void
    {
        Http::fake([
            'tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'token-1',
            ], 200),
            'tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'paymentID' => 'PAY-1',
                'bkashURL' => 'https://sandbox.bka.sh/checkout/PAY-1',
            ], 201),
        ]);

        $tenant = $this->createTenant('bKash Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'gateway' => 'bkash',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.session_id', 'PAY-1')
            ->assertJsonPath('data.checkout.mode', 'sandbox')
            ->assertJsonPath('data.checkout.url', 'https://sandbox.bka.sh/checkout/PAY-1');

        $invoiceId = $response->json('data.invoice.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'currency' => 'BDT',
            'status' => 'pending',
            'payment_method' => 'bkash',
        ]);

        Http::assertSent(function ($request) use ($invoiceId): bool {
            return $request->url() === 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create'
                && $request['amount'] === '19.00'
                && $request['currency'] === 'BDT';
        });
    }

    public function test_callback_executes_the_payment_and_settles_the_invoice(): void
    {
        $tenant = $this->createTenant('bKash Webhook Household');
        $invoice = $this->pendingInvoice($tenant);

        Http::fake([
            'tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'token-1',
            ], 200),
            'tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute' => Http::response([
                'statusCode' => '0000',
                'transactionStatus' => 'Completed',
                'trxID' => 'TRX-1',
                'merchantInvoiceNumber' => $invoice->invoice_number,
            ], 200),
        ]);

        $this->get('/api/v1/webhooks/payments/bkash?paymentID=PAY-1&status=success')
            ->assertRedirect(
                rtrim((string) config('app.frontend_url'), '/').'/billing?checkout=success',
            );

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(
            'family',
            Tenancy::bypass(fn (): ?string => $tenant->fresh()?->subscription_tier),
        );
    }

    public function test_failed_execute_is_rejected(): void
    {
        $tenant = $this->createTenant('bKash Failed Household');
        $invoice = $this->pendingInvoice($tenant);

        Http::fake([
            'tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'token-1',
            ], 200),
            'tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute' => Http::response([
                'statusCode' => '2062',
                'transactionStatus' => 'Failed',
                'statusMessage' => 'Payment failed',
            ], 200),
        ]);

        $this->post('/api/v1/webhooks/payments/bkash', ['paymentID' => 'PAY-2'])
            ->assertStatus(400);

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_platform_gateway_test_grants_a_bkash_token(): void
    {
        Http::fake([
            'tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant' => Http::response([
                'id_token' => 'token-1',
            ], 200),
        ]);

        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'bkash'])
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
            'currency' => 'BDT',
            'status' => 'pending',
            'tier' => 'family',
            'billing_period' => 'monthly',
            'payment_method' => 'bkash',
        ]);
    }
}
