<?php

namespace App\Payments;

use InvalidArgumentException;

/** Resolves the configured provider by key. */
final class PaymentGatewayManager
{
    public function gateway(string $key): PaymentGateway
    {
        return match ($key) {
            'stripe' => app(StripeGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$key}]."),
        };
    }
}
