<?php

namespace App\Payments;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SSLCommerz hosted checkout.
 *
 * A session is created at `/gwprocess/v4/api.php`; the invoice id travels in
 * `value_a` and comes back with the IPN. The IPN is trusted only after
 * `/validator/api/validationserverAPI.php` confirms the transaction.
 */
final class SslCommerzGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'sslcommerz';
    }

    public function isConfigured(): bool
    {
        return $this->storeId() !== null && $this->storePassword() !== null;
    }

    public function mode(): string
    {
        return config('services.sslcommerz.mode', 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    public function createCheckout(Invoice $invoice, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $invoice->loadMissing('tenant');

        $admin = $invoice->tenant?->members()
            ->with('user')
            ->where('role', 'tenant_admin')
            ->first()?->user;

        $tranId = $invoice->invoice_number.'-'.substr((string) now()->timestamp, -6);

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(20)
            ->post($this->base().'/gwprocess/v4/api.php', [
                'store_id' => $this->storeId(),
                'store_passwd' => $this->storePassword(),
                'total_amount' => number_format($invoice->amount_cents / 100, 2, '.', ''),
                'currency' => strtoupper($invoice->currency ?: 'BDT'),
                'tran_id' => $tranId,
                'success_url' => $successUrl,
                'fail_url' => $cancelUrl,
                'cancel_url' => $cancelUrl,
                'cus_name' => $admin?->name ?? ($invoice->tenant?->name ?? 'FairShare household'),
                'cus_email' => $admin?->email ?? (string) config('mail.from.address', 'billing@fairshare.app'),
                'cus_phone' => $admin?->phone ?? '00000000000',
                'cus_add1' => 'N/A',
                'cus_city' => 'N/A',
                'cus_postcode' => '0000',
                'cus_country' => 'Bangladesh',
                'shipping_method' => 'NO',
                'product_name' => "FairShare {$invoice->tier} plan",
                'product_category' => 'subscription',
                'product_profile' => 'general',
                'value_a' => (string) $invoice->getKey(),
                'value_b' => (string) $invoice->tenant_id,
                'value_c' => $invoice->tier,
            ]);

        $payload = $response->json();

        if ($response->failed() || ! is_array($payload) || ($payload['status'] ?? null) !== 'SUCCESS') {
            throw new RuntimeException(
                'SSLCommerz session failed: '
                .(is_array($payload) ? ($payload['failedreason'] ?? 'unknown error') : 'HTTP '.$response->status()),
            );
        }

        $url = $payload['GatewayPageURL'] ?? null;
        $sessionKey = $payload['sessionkey'] ?? $tranId;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('SSLCommerz returned no GatewayPageURL.');
        }

        return new CheckoutSession((string) $sessionKey, $url, $this->key(), $this->mode());
    }

    public function verifyWebhook(Request $request): PaymentEvent
    {
        $validationId = (string) $request->input('val_id', '');

        if ($validationId === '') {
            throw new RuntimeException('SSLCommerz IPN is missing val_id.');
        }

        $response = Http::acceptJson()->timeout(20)->get(
            $this->base().'/validator/api/validationserverAPI.php',
            [
                'val_id' => $validationId,
                'store_id' => $this->storeId(),
                'store_passwd' => $this->storePassword(),
                'format' => 'json',
                'v' => 1,
            ],
        );

        $payload = $response->json();

        if ($response->failed() || ! is_array($payload)) {
            throw new RuntimeException('SSLCommerz validation request failed.');
        }

        $status = strtoupper((string) ($payload['status'] ?? ''));
        $paid = in_array($status, ['VALID', 'VALIDATED'], true);

        if (! $paid) {
            throw new RuntimeException('SSLCommerz rejected the transaction: '.($status ?: 'unknown status'));
        }

        $invoiceId = $request->input('value_a')
            ?? ($payload['value_a'] ?? null);

        return new PaymentEvent(
            type: 'sslcommerz.ipn',
            paid: true,
            invoiceId: is_numeric($invoiceId) ? (int) $invoiceId : null,
            providerReference: (string) ($payload['bank_tran_id'] ?? ($payload['tran_id'] ?? $validationId)),
            payload: ['ipn' => $request->all(), 'validation' => $payload],
        );
    }

    public function confirm(PaymentEvent $event): PaymentEvent
    {
        return $event;
    }

    private function base(): string
    {
        return $this->mode() === 'live'
            ? 'https://securepay.sslcommerz.com'
            : 'https://sandbox.sslcommerz.com';
    }

    private function storeId(): ?string
    {
        $value = config('services.sslcommerz.store_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function storePassword(): ?string
    {
        $value = config('services.sslcommerz.store_password');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
