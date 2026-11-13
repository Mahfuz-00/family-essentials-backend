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
            'paypal' => app(PayPalGateway::class),
            'razorpay' => app(RazorpayGateway::class),
            'sslcommerz' => app(SslCommerzGateway::class),
            'bkash' => app(BkashGateway::class),
            'paystack' => app(PaystackGateway::class),
            'mollie' => app(MollieGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$key}]."),
        };
    }
}
