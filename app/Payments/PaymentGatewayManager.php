<?php

namespace App\Payments;

use InvalidArgumentException;

/** Resolves the configured provider by key. */
final class PaymentGatewayManager
{
    /**
     * @param  string|null  $mode  the mode to operate in; null means the gateway's client-facing mode
     */
    public function gateway(string $key, ?string $mode = null): PaymentGateway
    {
        $credentials = app(GatewayCredentials::class);

        return match ($key) {
            'stripe' => new StripeGateway($credentials, $mode),
            'paypal' => new PayPalGateway($credentials, $mode),
            'razorpay' => new RazorpayGateway($credentials, $mode),
            'sslcommerz' => new SslCommerzGateway($credentials, $mode),
            'bkash' => new BkashGateway($credentials, $mode),
            'paystack' => new PaystackGateway($credentials, $mode),
            'mollie' => new MollieGateway($credentials, $mode),
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$key}]."),
        };
    }
}
