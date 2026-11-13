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
    ) {}
}
