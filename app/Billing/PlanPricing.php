<?php

namespace App\Billing;

/**
 * The amounts the billing flow charges, in integer minor units.
 * Annual billing is ten monthly payments, as the existing upgrade preview does.
 */
final class PlanPricing
{
    private const MONTHLY_CENTS = [
        'starter' => 0,
        'plus' => 900,
        'family' => 1900,
        'family_plus' => 2900,
        'enterprise' => 4900,
    ];

    public static function amountCents(string $tier, string $period): int
    {
        $base = self::MONTHLY_CENTS[$tier] ?? self::MONTHLY_CENTS['family'];

        return $period === 'annual' ? (int) round($base * 10) : $base;
    }
}
