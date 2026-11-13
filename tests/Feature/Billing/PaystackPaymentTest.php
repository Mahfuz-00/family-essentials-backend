<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PaystackPaymentTest extends TestCase
{
    use CreatesTenants;

    private const SECRET = 'sk_test_paystack_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paystack.secret_key', self::SECRET);
    }

    public function test_checkout_returns_a_paystack_authorization_url(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/abc123',
                    'access_code' => 'abc123',
                    'reference' => 'REF-1',
                ],
            ], 200),
        ]);

        $tenant = $this->createTenant('Paystack Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'gateway' => 'paystack',
                'currency' => 'NGN',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.session_id', 'REF-1')
            ->assertJsonPath('data.checkout.mode', 'sandbox')
            ->assertJsonPath('data.checkout.url', 'https://checkout.paystack.com/abc123');

        $invoiceId = $response->json('data.invoice.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'currency' => 'NGN',
            'status' => 'pending',
            'payment_method' => 'paystack',
        ]);

        Http::assertSent(function ($request) use ($invoiceId): bool {
            return $request->url() === 'https://api.paystack.co/transaction/initialize'
                && $request['amount'] === 1900
                && $request['metadata']['invoice_id'] === (string) $invoiceId;
        });
    }

    public function test_signed_charge_success_settles_the_invoice(): void
    {
        $tenant = $this->createTenant('Paystack Webhook Household');
        $invoice = $this->pendingInvoice($tenant);
        $payload = $this->chargePayload($invoice);

        $this->postWebhook($payload, $this->signature($payload))
            ->assertOk()
            ->assertJsonPath('handled', true);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(
            'family',
            Tenancy::bypass(fn (): ?string => $tenant->fresh()?->subscription_tier),
        );
    }

    public function test_webhook_with_an_invalid_signature_is_rejected(): void
    {
        $tenant = $this->createTenant('Paystack Forged Household');
        $invoice = $this->pendingInvoice($tenant);
        $payload = $this->chargePayload($invoice);

        $this->postWebhook($payload, 'bad-signature')->assertStatus(400);

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_platform_gateway_test_calls_the_paystack_transactions_api(): void
    {
        Http::fake([
            'api.paystack.co/transaction*' => Http::response(['status' => true, 'data' => []], 200),
        ]);

        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'paystack'])
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
            'currency' => 'NGN',
            'status' => 'pending',
            'tier' => 'family',
            'billing_period' => 'monthly',
            'payment_method' => 'paystack',
        ]);
    }

    private function chargePayload(Invoice $invoice): string
    {
        return json_encode([
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'reference' => 'REF-1',
                'metadata' => ['invoice_id' => (string) $invoice->id],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function signature(string $payload): string
    {
        return hash_hmac('sha512', $payload, self::SECRET);
    }

    private function postWebhook(string $payload, string $signature)
    {
        return $this->call('POST', '/api/v1/webhooks/payments/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ], $payload);
    }
}
