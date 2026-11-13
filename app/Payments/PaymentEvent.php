<?php

namespace App\Payments;

/** A normalized provider webhook event. */
final class PaymentEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $type,
        public readonly bool $paid,
        public readonly ?int $invoiceId,
        public readonly string $providerReference,
        public readonly array $payload,
    ) {}
}
