<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Driver
    |--------------------------------------------------------------------------
    |
    | Phase 1 ships with a "log" driver that writes outgoing messages to the
    | application log. A real provider (Talkify / Twilio) is wired up in a
    | later phase behind the same SmsSender contract.
    |
    */

    'driver' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Phone Verification
    |--------------------------------------------------------------------------
    */

    'code_length' => (int) env('SMS_CODE_LENGTH', 6),
    'code_ttl_minutes' => (int) env('SMS_CODE_TTL_MINUTES', 10),
    'max_attempts' => (int) env('SMS_MAX_ATTEMPTS', 5),

];
