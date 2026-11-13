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
            'member_calls_cap' => 0,
            'chat_messages_cap' => 30,
            'receipt_scans_cap' => 5,
            'pantry_items_cap' => 20,
            'features' => [
                'Up to 2 family members',
                '2 saved stores & geofences',
                '50 errands per month',
                '30 AI & member chat messages/mo',
                '5 receipt OCR scans/mo',
                'Basic pantry tracker (20 items)',
                'Member-to-member calling not included',
            ],
        ],

        'pilot' => [
            'label' => 'Pilot Family Tier',
            'price' => '$14.99',
            'cadence' => 'per month',
            'seat_cap' => 5,
            'place_cap' => 10,
            'monthly_requests' => 300,
            'member_calls_cap' => 15,
            'chat_messages_cap' => 300,
            'receipt_scans_cap' => 50,
            'pantry_items_cap' => 100,
            'features' => [
                'Up to 5 family members',
                '10 saved stores & geofences',
                '300 errands per month',
                '15 member escalation calls/mo',
                '300 AI & member chat messages/mo',
                '50 receipt OCR scans/mo',
                'Full pantry inventory & cost tracking (100 items)',
                'Multi-period household expense ledgers',
            ],
        ],

        'standard' => [
            'label' => 'Household Pro',
            'price' => '$24.99',
            'cadence' => 'per month',
            'seat_cap' => 10,
            'place_cap' => 25,
            'monthly_requests' => 1000,
            'member_calls_cap' => 60,
            'chat_messages_cap' => 1500,
            'receipt_scans_cap' => 200,
            'pantry_items_cap' => 500,
            'features' => [
                'Up to 10 family members',
                '25 saved stores & geofences',
                '1,000 errands per month',
                '60 member escalation calls/mo',
                '1,500 AI & member chat messages/mo',
                '200 receipt OCR scans/mo',
                'Extended pantry & auto-restock buffer (500 items)',
                'Complete wealth, assets, debt & tax tracking',
                'Household Data Vault & Portability JSON export',
            ],
        ],

        'enterprise' => [
            'label' => 'Enterprise Household',
            'price' => '$49.99',
            'cadence' => 'per month',
            'seat_cap' => 25,
            'place_cap' => 100,
            'monthly_requests' => 5000,
            'member_calls_cap' => 9999,
            'chat_messages_cap' => 9999,
            'receipt_scans_cap' => 1000,
            'pantry_items_cap' => 2500,
            'features' => [
                'Up to 25 family members',
                '100 saved stores & geofences',
                '5,000 errands per month',
                'Unlimited member direct calling & escalation',
                'Unlimited AI family dispatcher & member chat',
                '1,000 receipt OCR scans/mo',
                'Pantry catalog up to 2,500 items',
                'Multi-generational property & estate tracking',
                'Priority platform support & concierge onboarding',
            ],
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
