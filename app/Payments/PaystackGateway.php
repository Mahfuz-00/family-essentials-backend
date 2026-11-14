<?php

namespace App\Payments;

use App\Models\Invoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paystack Transactions API.
 *
 * Checkout initializes a transaction and returns its authorization URL. The
 * callback is trusted only when its `x-paystack-signature` HMAC (SHA-512 of the
 * raw body with the secret key) matches.
 */
final class PaystackGateway implements PaymentGateway
{
    private const API_BASE = 'https://api.paystack.co';

    public function __construct(
        private readonly GatewayCredentials $credentials,
        private readonly ?string $mode = null,
    ) {}

    public function key(): string
    {
        return 'paystack';
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
        $invoice->loadMissing('tenant');

        $admin = $invoice->tenant?->members()
            ->with('user')
            ->where('role', 'tenant_admin')
            ->first()?->user;

        $response = Http::withToken((string) $this->secretKey())
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post(self::API_BASE.'/transaction/initialize', [
                'email' => $admin?->email ?? (string) config('mail.from.address', 'billing@fairshare.app'),
                'amount' => $invoice->amount_cents,
                'currency' => strtoupper($invoice->currency ?: 'USD'),
                'reference' => $invoice->invoice_number,
                'callback_url' => $successUrl,
                'metadata' => [
                    'invoice_id' => (string) $invoice->getKey(),
                    'tenant_id' => (string) $invoice->tenant_id,
                    'tier' => $invoice->tier,
                    'cancel_url' => $cancelUrl,
                ],
            ]);

        $payload = $response->json();

        if ($response->failed() || ! is_array($payload) || ($payload['status'] ?? null) !== true) {
            throw new RuntimeException(
                'Paystack initialize failed: '
                .(is_array($payload)
                    ? ($payload['message'] ?? 'unknown error')
                    : 'HTTP '.$response->status()),
            );
        }

        $data = $payload['data'] ?? [];
        $url = $data['authorization_url'] ?? null;
        $reference = $data['reference'] ?? ($data['access_code'] ?? null);

        if (! is_string($url) || ! is_string($reference)) {
            throw new RuntimeException('Paystack returned no authorization URL.');
        }

        return new CheckoutSession($reference, $url, $this->key(), $this->mode());
    }

    public function verifyTestPayment(string $reference): array
    {
        $secret = $this->secretKey();

        if ($secret === null) {
            return ['status' => 'unknown', 'message' => 'Paystack is not configured: the secret key is missing.'];
        }

        try {
            $response = Http::withToken($secret)
                ->acceptJson()
                ->timeout(20)
                ->get(self::API_BASE.'/transaction/verify/'.rawurlencode($reference));
        } catch (ConnectionException $exception) {
            return ['status' => 'unknown', 'message' => 'Could not reach Paystack: '.$exception->getMessage()];
        }

        if ($response->failed()) {
            return [
                'status' => 'unknown',
                'message' => 'Paystack rejected the status lookup: '
                    .($response->json('message') ?? 'HTTP '.$response->status()),
            ];
        }

        // Paystack answers `status: true` plus `data.status`; only the inner value
        // says whether the money moved.
        $status = strtolower((string) $response->json('data.status'));

        if ($status === 'success') {
            return ['status' => 'paid', 'message' => 'Paystack confirms the transaction succeeded.'];
        }

        if ($status === 'failed') {
            return ['status' => 'failed', 'message' => 'Paystack reports the transaction failed.'];
        }

        return ['status' => 'pending', 'message' => 'Paystack is still waiting for the payment.'];
    }

    public function verifyWebhook(Request $request): PaymentEvent
    {
        $secret = $this->secretKey();

        if ($secret === null) {
            throw new RuntimeException('Paystack secret key is missing.');
        }

        $payload = $request->getContent();
        $signature = (string) $request->header('x-paystack-signature', '');

        if ($signature === '' || ! hash_equals(hash_hmac('sha512', $payload, $secret), $signature)) {
            throw new RuntimeException('Invalid Paystack signature.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new RuntimeException('Paystack webhook payload is not valid JSON.');
        }

        $type = (string) ($event['event'] ?? 'unknown');
        $data = $event['data'] ?? [];
        $data = is_array($data) ? $data : [];

        $metadata = $data['metadata'] ?? [];
        $metadata = is_array($metadata) ? $metadata : [];
        $invoiceId = $metadata['invoice_id'] ?? null;

        return new PaymentEvent(
            type: $type,
            paid: $type === 'charge.success' && ($data['status'] ?? null) === 'success',
            invoiceId: is_numeric($invoiceId) ? (int) $invoiceId : null,
            providerReference: (string) ($data['reference'] ?? ''),
            payload: $event,
        );
    }

    public function confirm(PaymentEvent $event): PaymentEvent
    {
        return $event;
    }

    private function secretKey(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['secret_key'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
