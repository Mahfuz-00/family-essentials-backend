<?php

namespace App\Payments;

use App\Models\Invoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout over the REST API — no SDK, no stored card data.
 *
 * A payment is only trusted after a webhook whose `Stripe-Signature` header is
 * verified against the endpoint secret. The checkout URL is created here; the
 * invoice is settled by the webhook, never by the redirect back to the app.
 */
final class StripeGateway implements PaymentGateway
{
    private const API_BASE = 'https://api.stripe.com/v1';

    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function __construct(
        private readonly GatewayCredentials $credentials,
        private readonly ?string $mode = null,
    ) {}

    public function key(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return $this->credentials->isConfigured($this->key(), $this->mode());
    }

    public function mode(): string
    {
        return $this->mode ?? $this->credentials->clientMode($this->key());
    }

    public function createCheckout(Invoice $invoice, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $secret = $this->requireSecret();
        $invoice->loadMissing('tenant');

        try {
            $response = Http::withToken($secret)
                ->asForm()
                ->acceptJson()
                ->timeout(20)
                ->post(self::API_BASE.'/checkout/sessions', [
                    'mode' => 'payment',
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'client_reference_id' => (string) $invoice->getKey(),
                    'line_items' => [[
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => strtolower($invoice->currency ?: 'usd'),
                            'unit_amount' => $invoice->amount_cents,
                            'product_data' => [
                                'name' => sprintf(
                                    'FairShare %s plan (%s)',
                                    $invoice->tier,
                                    $invoice->billing_period,
                                ),
                            ],
                        ],
                    ]],
                    'metadata' => [
                        'invoice_id' => (string) $invoice->getKey(),
                        'tenant_id' => (string) $invoice->tenant_id,
                        'tier' => $invoice->tier,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach Stripe: '.$exception->getMessage(), 0, $exception);
        }

        if ($response->failed()) {
            $message = $response->json('error.message') ?? 'HTTP '.$response->status();

            throw new RuntimeException('Stripe checkout session failed: '.$message);
        }

        $id = $response->json('id');
        $url = $response->json('url');

        if (! is_string($id) || ! is_string($url)) {
            throw new RuntimeException('Stripe returned an incomplete checkout session.');
        }

        return new CheckoutSession($id, $url, $this->key(), $this->mode());
    }

    public function verifyTestPayment(string $reference): array
    {
        $secret = $this->secret();

        if ($secret === null) {
            return ['status' => 'unknown', 'message' => 'Stripe is not configured: the secret key is missing.'];
        }

        try {
            $response = Http::withToken($secret)
                ->acceptJson()
                ->timeout(20)
                ->get(self::API_BASE.'/checkout/sessions/'.rawurlencode($reference));
        } catch (ConnectionException $exception) {
            return ['status' => 'unknown', 'message' => 'Could not reach Stripe: '.$exception->getMessage()];
        }

        if ($response->failed()) {
            return [
                'status' => 'unknown',
                'message' => 'Stripe rejected the status lookup: '
                    .($response->json('error.message') ?? 'HTTP '.$response->status()),
            ];
        }

        $paymentStatus = $response->json('payment_status');
        $status = $response->json('status');

        if ($paymentStatus === 'paid' || $status === 'complete') {
            return ['status' => 'paid', 'message' => 'Stripe confirms the checkout session was paid.'];
        }

        if ($status === 'expired') {
            return ['status' => 'failed', 'message' => 'The Stripe checkout session expired before it was paid.'];
        }

        return ['status' => 'pending', 'message' => 'Stripe is still waiting for the payment.'];
    }

    public function verifyWebhook(Request $request): PaymentEvent
    {
        $secret = $this->requireWebhookSecret();
        $payload = $request->getContent();

        $this->assertSignature($payload, (string) $request->header('Stripe-Signature', ''), $secret);

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new RuntimeException('Stripe webhook payload is not valid JSON.');
        }

        $type = (string) ($event['type'] ?? 'unknown');
        $object = $event['data']['object'] ?? [];
        $object = is_array($object) ? $object : [];

        $paid = in_array($type, [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
        ], true) && (
            ($object['payment_status'] ?? null) === 'paid'
            || $type === 'checkout.session.async_payment_succeeded'
        );

        $reference = $object['client_reference_id'] ?? ($object['metadata']['invoice_id'] ?? null);

        return new PaymentEvent(
            type: $type,
            paid: $paid,
            invoiceId: is_numeric($reference) ? (int) $reference : null,
            providerReference: (string) ($object['id'] ?? ''),
            payload: $event,
        );
    }

    public function confirm(PaymentEvent $event): PaymentEvent
    {
        return $event;
    }

    private function assertSignature(string $payload, string $header, string $secret): void
    {
        $parts = [];

        foreach (explode(',', $header) as $chunk) {
            [$key, $value] = array_pad(explode('=', trim($chunk), 2), 2, null);

            if ($key !== null && $value !== null) {
                $parts[$key][] = $value;
            }
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);
        $signatures = $parts['v1'] ?? [];

        if ($timestamp === 0 || $signatures === []) {
            throw new RuntimeException('Missing Stripe signature header.');
        }

        if (abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            throw new RuntimeException('Stripe signature timestamp is outside the tolerance window.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new RuntimeException('Invalid Stripe signature.');
    }

    private function secret(): ?string
    {
        $secret = $this->credentials->resolve($this->key(), $this->mode())['secret_key'] ?? null;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    private function webhookSecret(): ?string
    {
        $secret = $this->credentials->resolve($this->key(), $this->mode())['webhook_secret'] ?? null;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    private function requireSecret(): string
    {
        $secret = $this->secret();

        if ($secret === null) {
            throw new RuntimeException('Stripe is not configured: STRIPE_SECRET is missing.');
        }

        return $secret;
    }

    private function requireWebhookSecret(): string
    {
        $secret = $this->webhookSecret();

        if ($secret === null) {
            throw new RuntimeException('Stripe webhook secret is missing.');
        }

        return $secret;
    }
}
