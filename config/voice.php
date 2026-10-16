<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Voice Driver
    |--------------------------------------------------------------------------
    |
    | "log" writes outgoing calls to the application log; "fake" is the test
    | mode driver with deterministic outcomes. A real provider driver
    | (talkify|twilio) implements the same VoiceCaller contract.
    |
    */

    'driver' => env('VOICE_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Escalation Bounds
    |--------------------------------------------------------------------------
    |
    | These exist so a household can never be spam-called (PRD §2).
    |
    */

    'max_attempts_per_request' => (int) env('VOICE_MAX_ATTEMPTS_PER_REQUEST', 2),
    'daily_budget' => (int) env('VOICE_DAILY_BUDGET', 10),
    'cooldown_minutes' => (int) env('VOICE_COOLDOWN_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | Shared secret used to verify the provider callback signature.
    |
    */

    'webhook_secret' => env('VOICE_WEBHOOK_SECRET', 'dev-voice-secret'),

];
