<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plan catalogue — the server's copy of the truth
    |--------------------------------------------------------------------------
    |
    | These caps are ENFORCED (see App\Plans\QuotaService), which is why they live
    | here rather than only in the dashboard's presentation catalogue. The labels
    | and prices stay presentation-only: there is no payment provider, so nothing
    | here is billed.
    |
    | `tenants.subscription_tier` holds one of these keys. Any value outside the
    | list is rejected at validation, so a typo can no longer create a tenant that
    | silently resolves to a default plan.
    |
    */

    'default' => env('PLAN_DEFAULT_TIER', 'pilot'),

    'tiers' => [

        'starter' => [
            'label' => 'Starter Family',
            'price' => '$0',
            'cadence' => 'free',
            'seat_cap' => 2,
            'place_cap' => 2,
            'monthly_requests' => 50,
        ],

        'pilot' => [
            'label' => 'Pilot Family Tier',
            'price' => '$14.99',
            'cadence' => 'per month',
            'seat_cap' => 5,
            'place_cap' => 10,
            'monthly_requests' => 300,
        ],

        'standard' => [
            'label' => 'Household Pro',
            'price' => '$24.99',
            'cadence' => 'per month',
            'seat_cap' => 10,
            'place_cap' => 25,
            'monthly_requests' => 1000,
        ],

        'enterprise' => [
            'label' => 'Enterprise Household',
            'price' => '$49.99',
            'cadence' => 'per month',
            'seat_cap' => 25,
            'place_cap' => 100,
            'monthly_requests' => 5000,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Enforcement
    |--------------------------------------------------------------------------
    |
    | Enforcement can be switched off in one place, which is what the seeder and
    | the platform provisioner want: seeding a household is not a customer action
    | and must not be refused for exceeding a plan.
    |
    */

    'enforce' => env('PLAN_ENFORCE', true),

];
