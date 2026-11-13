<?php

namespace App\Payments;

use App\Models\Invoice;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PayPal Orders v2 over the REST API.
 *
 * Checkout creates an order and returns its approval link. Webhooks are checked
 * with PayPal's own `verify-webhook-signature` endpoint; an approved order is
 * captured before the invoice is settled.
 */
final class PayPalGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'paypal';
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== null
            && $this->clientSecret() !== null
            && $this->webhookId() !== null;
    }

    public function mode(): string
    {
        return config('services.paypal.mode', 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    public function createCheckout(Invoice $invoice, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $invoice->loadMissing('tenant');

        $response = $this->client()->post($this->base().'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id' => (string) $invoice->getKey(),
                'description' => sprintf('FairShare %s plan (%s)', $invoice->tier, $invoice->billing_period),
                'amount' => [
                    'currency_code' => strtoupper($invoice->currency ?: 'USD'),
                    'value' => number_format($invoice->amount_cents / 100, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'user_action' => 'PAY_NOW',
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'PayPal order failed: '.($response->json('message') ?? 'HTTP '.$response->status()),
            );
        }

        $orderId = $response->json('id');
        $approve = collect($response->json('links') ?? [])
            ->firstWhere('rel', 'approve');
        $url = is_array($approve) ? ($approve['href'] ?? null) : null;

        if (! is_string($orderId) || ! is_string($url)) {
            throw new RuntimeException('PayPal returned an order without an approval link.');
        }

        return new CheckoutSession($orderId, $url, $this->key(), $this->mode());
    }

    public function verifyWebhook(Request $request): PaymentEvent
    {
        $webhookId = $this->webhookId();

        if ($webhookId === null) {
            throw new RuntimeException('PayPal webhook id is missing.');
        }

        $payload = $request->getContent();
        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new RuntimeException('PayPal webhook payload is not valid JSON.');
        }

        $verification = $this->client()->post($this->base().'/v1/notifications/verify-webhook-signature', [
            'auth_algo' => (string) $request->header('PAYPAL-AUTH-ALGO', ''),
            'cert_url' => (string) $request->header('PAYPAL-CERT-URL', ''),
            'transmission_id' => (string) $request->header('PAYPAL-TRANSMISSION-ID', ''),
            'transmission_sig' => (string) $request->header('PAYPAL-TRANSMISSION-SIG', ''),
            'transmission_time' => (string) $request->header('PAYPAL-TRANSMISSION-TIME', ''),
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ]);

        if (! $verification->successful() || $verification->json('verification_status') !== 'SUCCESS') {
            throw new RuntimeException('PayPal webhook signature could not be verified.');
        }

        $type = (string) ($event['event_type'] ?? 'unknown');
        $resource = $event['resource'] ?? [];
        $resource = is_array($resource) ? $resource : [];

        $invoiceId = $this->invoiceIdFrom($resource);
        $paid = $type === 'PAYMENT.CAPTURE.COMPLETED'
            || $type === 'CHECKOUT.ORDER.COMPLETED';

        return new PaymentEvent(
            type: $type,
            paid: $paid,
            invoiceId: $invoiceId,
            providerReference: (string) ($resource['id'] ?? ''),
            payload: $event,
        );
    }

    public function confirm(PaymentEvent $event): PaymentEvent
    {
        if ($event->paid || $event->type !== 'CHECKOUT.ORDER.APPROVED') {
            return $event;
        }

        $orderId = $event->payload['resource']['id'] ?? null;

        if (! is_string($orderId) || $orderId === '') {
            return $event;
        }

        $capture = $this->client()->post($this->base()."/v2/checkout/orders/{$orderId}/capture", []);

        if (! $capture->successful() || $capture->json('status') !== 'COMPLETED') {
            return $event;
        }

        return new PaymentEvent(
            type: 'PAYMENT.CAPTURE.COMPLETED',
            paid: true,
            invoiceId: $event->invoiceId,
            providerReference: (string) ($capture->json('id') ?? $orderId),
            payload: $event->payload,
        );
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function invoiceIdFrom(array $resource): ?int
    {
        $custom = $resource['custom_id']
            ?? ($resource['purchase_units'][0]['custom_id'] ?? null);

        return is_numeric($custom) ? (int) $custom : null;
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    private function accessToken(): string
    {
        $response = Http::withBasicAuth((string) $this->clientId(), (string) $this->clientSecret())
            ->asForm()
            ->acceptJson()
            ->timeout(20)
            ->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        $token = $response->json('access_token');

        if ($response->failed() || ! is_string($token) || $token === '') {
            throw new RuntimeException(
                'PayPal authentication failed: '.($response->json('error_description') ?? 'HTTP '.$response->status()),
            );
        }

        return $token;
    }

    private function base(): string
    {
        return $this->mode() === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function clientId(): ?string
    {
        $value = config('services.paypal.client_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function clientSecret(): ?string
    {
        $value = config('services.paypal.client_secret');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function webhookId(): ?string
    {
        $value = config('services.paypal.webhook_id');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
