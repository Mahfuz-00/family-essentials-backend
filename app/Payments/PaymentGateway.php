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
     * Poll one checkout the provider is holding open, without settling anything
     * locally. This exists for the platform console's sandbox payment test, and
     * it never throws: an unreachable or unhappy provider is reported, not
     * raised.
     *
     * `status` is one of:
     *  - `paid`    — the provider confirms the money moved;
     *  - `pending` — still waiting (open session, approved but not captured, …);
     *  - `failed`  — the provider says it will never complete (expired, cancelled);
     *  - `unknown` — the provider could not be asked, or answered unintelligibly.
     *
     * `message` is a human-readable sentence for the console to show.
     *
     * @param  string  $reference  the CheckoutSession reference (see CheckoutSession::reference())
     * @return array{status: 'paid'|'pending'|'failed'|'unknown', message: string}
     */
    public function verifyTestPayment(string $reference): array;

    /**
     * Verify the provider signature and normalize the event.
     *
     * @throws \RuntimeException when the signature is missing, stale or invalid
     */
    public function verifyWebhook(Request $request): PaymentEvent;

    /**
     * Finish any provider-side step before settlement (for example PayPal's
     * capture of an approved order). Stripe needs no confirmation.
     */
    public function confirm(PaymentEvent $event): PaymentEvent;
}
