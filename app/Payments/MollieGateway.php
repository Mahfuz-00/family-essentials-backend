<?php

namespace App\Payments;

use App\Models\Invoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Mollie Payments API.
 *
 * Mollie's webhook carries only a payment id and no signature, so the callback
 * is never trusted on its own: the payment is fetched back from Mollie and its
 * status and metadata are checked before settlement.
 */
final class MollieGateway implements PaymentGateway
{
    private const API_BASE = 'https://api.mollie.com/v2';

    public function __construct(
        private readonly GatewayCredentials $credentials,
        private readonly ?string $mode = null,
    ) {}

    public function key(): string
    {
        return 'mollie';
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
        $response = Http::withToken((string) $this->apiKey())
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post(self::API_BASE.'/payments', [
                'amount' => [
                    'currency' => strtoupper($invoice->currency ?: 'EUR'),
                    'value' => number_format($invoice->amount_cents / 100, 2, '.', ''),
                ],
                'description' => sprintf('FairShare %s plan (%s)', $invoice->tier, $invoice->billing_period),
                'redirectUrl' => $successUrl,
                'webhookUrl' => $this->webhookUrl(),
                'sequenceType' => 'oneoff',
                'metadata' => [
                    'invoice_id' => (string) $invoice->getKey(),
                    'tenant_id' => (string) $invoice->tenant_id,
                    'tier' => $invoice->tier,
                    'cancel_url' => $cancelUrl,
                ],
            ]);

        $payload = $response->json();

        if ($response->failed() || ! is_array($payload)) {
            throw new RuntimeException(
                'Mollie payment failed: '
                .(is_array($payload) ? ($payload['detail'] ?? 'unknown error') : 'HTTP '.$response->status()),
            );
        }

        $id = $payload['id'] ?? null;
        $url = $payload['_links']['checkout']['href'] ?? null;

        if (! is_string($id) || ! is_string($url)) {
            throw new RuntimeException('Mollie returned a payment without a checkout URL.');
        }

        return new CheckoutSession($id, $url, $this->key(), $this->mode());
    }

    public function verifyTestPayment(string $reference): array
    {
        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            return ['status' => 'unknown', 'message' => 'Mollie is not configured: the API key is missing.'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20)
                ->get(self::API_BASE.'/payments/'.rawurlencode($reference));
        } catch (ConnectionException $exception) {
            return ['status' => 'unknown', 'message' => 'Could not reach Mollie: '.$exception->getMessage()];
        }

        if ($response->failed()) {
            return [
                'status' => 'unknown',
                'message' => 'Mollie rejected the status lookup: '
                    .($response->json('detail') ?? 'HTTP '.$response->status()),
            ];
        }

        $status = strtolower((string) $response->json('status'));

        if ($status === 'paid') {
            return ['status' => 'paid', 'message' => 'Mollie confirms the payment was paid.'];
        }

        if (in_array($status, ['failed', 'canceled', 'expired'], true)) {
            return ['status' => 'failed', 'message' => "The Mollie payment ended as {$status}."];
        }

        return ['status' => 'pending', 'message' => 'Mollie is still waiting for the payment.'];
    }

    public function verifyWebhook(Request $request): PaymentEvent
    {
        $paymentId = (string) $request->input('id', '');

        if ($paymentId === '') {
            throw new RuntimeException('Mollie webhook is missing the payment id.');
        }

        $response = Http::withToken((string) $this->apiKey())
            ->acceptJson()
            ->timeout(20)
            ->get(self::API_BASE.'/payments/'.$paymentId);

        $payload = $response->json();

        if ($response->failed() || ! is_array($payload)) {
            throw new RuntimeException('Mollie payment lookup failed.');
        }

        if (($payload['status'] ?? null) !== 'paid') {
            throw new RuntimeException(
                'Mollie payment is not paid: '.($payload['status'] ?? 'unknown status'),
            );
        }

        $metadata = $payload['metadata'] ?? [];
        $metadata = is_array($metadata) ? $metadata : [];
        $invoiceId = $metadata['invoice_id'] ?? null;

        return new PaymentEvent(
            type: 'mollie.payment.paid',
            paid: true,
            invoiceId: is_numeric($invoiceId) ? (int) $invoiceId : null,
            providerReference: (string) ($payload['id'] ?? $paymentId),
            payload: $payload,
        );
    }

    public function confirm(PaymentEvent $event): PaymentEvent
    {
        return $event;
    }

    private function apiKey(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['api_key'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function webhookUrl(): string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['webhook_url'] ?? null;

        return is_string($value) && $value !== ''
            ? $value
            : url('/api/v1/webhooks/payments/mollie');
    }
}
