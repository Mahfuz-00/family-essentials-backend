<?php

namespace App\Payments;

/** A hosted checkout the client redirects to. */
final class CheckoutSession
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $gateway,
        public readonly string $mode,
        /**
         * What the provider's status API is queried by. Providers that poll by
         * something other than the session id — SSLCommerz looks a payment up by
         * `tran_id` — pass it here; everyone else leaves it null and `id` stands.
         */
        public readonly ?string $reference = null,
    ) {}

    /** The handle a later status check must use. */
    public function reference(): string
    {
        return $this->reference === null || $this->reference === '' ? $this->id : $this->reference;
    }
}
