<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class RazorpayPaymentTest extends TestCase
{
    use CreatesTenants;

    private const WEBHOOK_SECRET = 'rzp-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.razorpay.key_id', 'rzp_test_fairshare');
        config()->set('services.razorpay.key_secret', 'rzp-secret');
        config()->set('services.razorpay.webhook_secret', self::WEBHOOK_SECRET);
    }

    public function test_checkout_returns_a_razorpay_payment_link(): void
    {
        Http::fake([
            'api.razorpay.com/v1/payment_links' => Http::response([
                'id' => 'plink_1',
                'short_url' => 'https://rzp.io/l/plink_1',
            ], 200),
        ]);

        $tenant = $this->createTenant('Razorpay Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/checkout-session', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'gateway' => 'razorpay',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout.session_id', 'plink_1')
            ->assertJsonPath('data.checkout.mode', 'sandbox')
            ->assertJsonPath('data.checkout.url', 'https://rzp.io/l/plink_1');

        $invoiceId = $response->json('data.invoice.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'pending',
            'payment_method' => 'razorpay',
        ]);

        Http::assertSent(function ($request) use ($invoiceId): bool {
            return $request->url() === 'https://api.razorpay.com/v1/payment_links'
                && $request['amount'] === 1900
                && $request['notes']['invoice_id'] === (string) $invoiceId;
        });
    }

    public function test_signed_payment_link_webhook_settles_the_invoice(): void
    {
        $tenant = $this->createTenant('Razorpay Webhook Household');
        $invoice = $this->pendingInvoice($tenant);

        $response = $this->postWebhook($this->paidPayload($invoice), $this->signature($this->paidPayload($invoice)));

        $response->assertOk()->assertJsonPath('handled', true);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(
            'family',
            Tenancy::bypass(fn (): ?string => $tenant->fresh()?->subscription_tier),
        );
    }

    public function test_webhook_with_an_invalid_signature_is_rejected(): void
    {
        $tenant = $this->createTenant('Razorpay Forged Household');
        $invoice = $this->pendingInvoice($tenant);
        $payload = $this->paidPayload($invoice);

        $this->postWebhook($payload, 'not-the-real-signature')->assertStatus(400);

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_platform_gateway_test_calls_the_razorpay_orders_api(): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders*' => Http::response(['entity' => 'collection', 'items' => []], 200),
        ]);

        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'razorpay'])
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
            'payment_method' => 'razorpay',
        ]);
    }

    private function paidPayload(Invoice $invoice): string
    {
        return json_encode([
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => [
                    'entity' => [
                        'id' => 'plink_1',
                        'notes' => ['invoice_id' => (string) $invoice->id],
                    ],
                ],
                'payment' => [
                    'entity' => [
                        'id' => 'pay_1',
                        'notes' => ['invoice_id' => (string) $invoice->id],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function signature(string $payload): string
    {
        return hash_hmac('sha256', $payload, self::WEBHOOK_SECRET);
    }

    private function postWebhook(string $payload, string $signature)
    {
        return $this->call('POST', '/api/v1/webhooks/payments/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        ], $payload);
    }
}
