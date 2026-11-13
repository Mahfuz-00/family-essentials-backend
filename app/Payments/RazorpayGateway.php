<?php

namespace App\Payments;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Razorpay Payment Links over the REST API.
 *
 * The hosted link carries the invoice id in `notes`; the webhook is verified
 * with the endpoint secret (`X-Razorpay-Signature`) before settlement.
 */
final class RazorpayGateway implements PaymentGateway
{
    private const API_BASE = 'https://api.razorpay.com/v1';

    public function key(): string
    {
        return 'razorpay';
    }

    public function isConfigured(): bool
    {
        return $this->keyId() !== null
            && $this->keySecret() !== null
            && $this->webhookSecret() !== null;
    }

    public function mode(): string
    {
        return str_starts_with((string) $this->keyId(), 'rzp_live_') ? 'live' : 'sandbox';
    }

    public function createCheckout(Invoice $invoice, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $invoice->loadMissing('tenant');

        $response = Http::withBasicAuth((string) $this->keyId(), (string) $this->keySecret())
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post(self::API_BASE.'/payment_links', [
                'amount' => $invoice->amount_cents,
                'currency' => strtoupper($invoice->currency ?: 'INR'),
                'accept_partial' => false,
                'reference_id' => $invoice->invoice_number,
                'description' => sprintf('FairShare %s plan (%s)', $invoice->tier, $invoice->billing_period),
                'callback_url' => $successUrl,
                'callback_method' => 'get',
                'reminder_enable' => false,
                'notes' => [
                    'invoice_id' => (string) $invoice->getKey(),
                    'tenant_id' => (string) $invoice->tenant_id,
                    'tier' => $invoice->tier,
                    'cancel_url' => $cancelUrl,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Razorpay payment link failed: '
                .($response->json('error.description') ?? 'HTTP '.$response->status()),
            );
        }

        $id = $response->json('id');
        $url = $response->json('short_url');

        if (! is_string($id) || ! is_string($url)) {
            throw new RuntimeException('Razorpay returned a payment link without a short URL.');
        }

        return new CheckoutSession($id, $url, $this->key(), $this->mode());
    }

    public function verifyWebhook(Request $request): PaymentEvent
    {
        $secret = $this->webhookSecret();

        if ($secret === null) {
            throw new RuntimeException('Razorpay webhook secret is missing.');
        }

        $payload = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');

        if ($signature === '' || ! hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            throw new RuntimeException('Invalid Razorpay signature.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new RuntimeException('Razorpay webhook payload is not valid JSON.');
        }

        $type = (string) ($event['event'] ?? 'unknown');
        $envelope = $event['payload'] ?? [];
        $envelope = is_array($envelope) ? $envelope : [];

        $link = $envelope['payment_link']['entity'] ?? [];
        $payment = $envelope['payment']['entity'] ?? [];
        $link = is_array($link) ? $link : [];
        $payment = is_array($payment) ? $payment : [];

        $notes = $payment['notes'] ?? ($link['notes'] ?? []);
        $notes = is_array($notes) ? $notes : [];
        $invoiceId = $notes['invoice_id'] ?? null;

        $paid = in_array($type, ['payment_link.paid', 'payment.captured', 'order.paid'], true);

        return new PaymentEvent(
            type: $type,
            paid: $paid,
            invoiceId: is_numeric($invoiceId) ? (int) $invoiceId : null,
            providerReference: (string) ($payment['id'] ?? ($link['id'] ?? '')),
            payload: $event,
        );
    }

    public function confirm(PaymentEvent $event): PaymentEvent
    {
        return $event;
    }

    private function keyId(): ?string
    {
        $value = config('services.razorpay.key_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function keySecret(): ?string
    {
        $value = config('services.razorpay.key_secret');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function webhookSecret(): ?string
    {
        $value = config('services.razorpay.webhook_secret');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
