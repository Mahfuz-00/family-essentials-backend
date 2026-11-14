<?php

namespace App\Payments;

use App\Models\Invoice;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * bKash Tokenized Checkout.
 *
 * The hosted page returns `paymentID`; the gateway executes it server-to-server
 * and settles the invoice only when bKash reports a completed transaction whose
 * `merchantInvoiceNumber` matches ours.
 */
final class BkashGateway implements PaymentGateway
{
    public function __construct(
        private readonly GatewayCredentials $credentials,
        private readonly ?string $mode = null,
    ) {}

    public function key(): string
    {
        return 'bkash';
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
        $currency = strtoupper($invoice->currency ?: 'BDT');

        if ($currency !== 'BDT') {
            throw new RuntimeException('bKash requires the invoice currency to be BDT.');
        }

        $response = $this->authorized()->post($this->base().'/tokenized/checkout/create', [
            'mode' => '0011',
            'payerReference' => (string) $invoice->tenant_id,
            // bKash returns the browser to callbackURL with paymentID; the
            // webhook controller executes it and then redirects to the app.
            'callbackURL' => url('/api/v1/webhooks/payments/bkash'),
            'amount' => number_format($invoice->amount_cents / 100, 2, '.', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $invoice->invoice_number,
        ]);

        $payload = $response->json();

        if ($response->failed() || ! is_array($payload) || ($payload['statusCode'] ?? null) !== '0000') {
            throw new RuntimeException(
                'bKash create payment failed: '
                .(is_array($payload)
                    ? ($payload['statusMessage'] ?? 'unknown error')
                    : 'HTTP '.$response->status()),
            );
        }

        $paymentId = $payload['paymentID'] ?? null;
        $url = $payload['bkashURL'] ?? null;

        if (! is_string($paymentId) || ! is_string($url)) {
            throw new RuntimeException('bKash returned no payment id or bkashURL.');
        }

        return new CheckoutSession($paymentId, $url, $this->key(), $this->mode());
    }

    public function verifyWebhook(Request $request): PaymentEvent
    {
        $paymentId = (string) ($request->input('paymentID') ?? $request->input('paymentId') ?? '');

        if ($paymentId === '') {
            throw new RuntimeException('bKash callback is missing paymentID.');
        }

        $response = $this->authorized()->post($this->base().'/tokenized/checkout/execute', [
            'paymentID' => $paymentId,
        ]);

        $payload = $response->json();

        if ($response->failed() || ! is_array($payload)) {
            throw new RuntimeException('bKash execute request failed.');
        }

        $completed = ($payload['statusCode'] ?? null) === '0000'
            && ($payload['transactionStatus'] ?? null) === 'Completed';

        if (! $completed) {
            throw new RuntimeException(
                'bKash did not complete the payment: '
                .($payload['statusMessage'] ?? 'unknown status'),
            );
        }

        $invoiceNumber = (string) ($payload['merchantInvoiceNumber'] ?? '');
        $invoice = $invoiceNumber === ''
            ? null
            : Invoice::where('invoice_number', $invoiceNumber)->first();

        return new PaymentEvent(
            type: 'bkash.execute',
            paid: true,
            invoiceId: $invoice?->getKey(),
            providerReference: (string) ($payload['trxID'] ?? $paymentId),
            payload: ['callback' => $request->all(), 'execute' => $payload],
        );
    }

    public function confirm(PaymentEvent $event): PaymentEvent
    {
        return $event;
    }

    private function authorized(): PendingRequest
    {
        $token = $this->grantToken();

        return Http::withHeaders([
            'Authorization' => $token,
            'X-APP-Key' => (string) $this->appKey(),
        ])->acceptJson()->asJson()->timeout(20);
    }

    private function grantToken(): string
    {
        $response = Http::withHeaders([
            'username' => (string) $this->username(),
            'password' => (string) $this->password(),
        ])->acceptJson()->asJson()->timeout(20)->post(
            $this->base().'/tokenized/checkout/token/grant',
            [
                'app_key' => (string) $this->appKey(),
                'app_secret' => (string) $this->appSecret(),
            ],
        );

        $token = $response->json('id_token');

        if ($response->failed() || ! is_string($token) || $token === '') {
            throw new RuntimeException(
                'bKash token grant failed: '
                .($response->json('statusMessage') ?? 'HTTP '.$response->status()),
            );
        }

        return $token;
    }

    private function base(): string
    {
        return $this->mode() === 'live'
            ? 'https://tokenized.pay.bka.sh/v1.2.0-beta'
            : 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';
    }

    private function appKey(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['app_key'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function appSecret(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['app_secret'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function username(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['username'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function password(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['password'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
