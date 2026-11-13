<?php

namespace App\Payments;

use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * A hosted-checkout provider. Implementations place a charge on the provider's
 * side and only settle the local invoice after a verified webhook.
 */
interface PaymentGateway
{
    public function key(): string;

    public function isConfigured(): bool;

    /** "sandbox" or "live", derived from the credentials in use. */
    public function mode(): string;

    public function createCheckout(Invoice $invoice, string $successUrl, string $cancelUrl): CheckoutSession;

    /**
     * Verify the provider signature and normalize the event.
     *
     * @throws \RuntimeException when the signature is missing, stale or invalid
     */
    public function verifyWebhook(Request $request): PaymentEvent;
}
