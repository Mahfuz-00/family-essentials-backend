<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Per-platform delivery
    |--------------------------------------------------------------------------
    |
    | Android goes through FCM, iOS through APNs. Either can be set to `log`,
    | which is the default so a fresh clone runs end to end without credentials
    | and the notification log stays truthful about what was attempted.
    |
    | `fcm` is also valid for iOS when you would rather let Firebase relay to
    | APNs — the sender picks by token platform, so either arrangement works.
    |
    */

    'android' => env('PUSH_ANDROID', env('PUSH_DRIVER', 'log')),
    'ios' => env('PUSH_IOS', env('PUSH_DRIVER', 'log')),

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        // Absolute path to the Firebase service-account JSON.
        'credentials' => env('FCM_CREDENTIALS_PATH'),
    ],

    'apns' => [
        // Absolute path to the .p8 auth key downloaded from Apple.
        'key_path' => env('APNS_KEY_PATH'),
        'key_id' => env('APNS_KEY_ID'),
        'team_id' => env('APNS_TEAM_ID'),
        'bundle_id' => env('APNS_BUNDLE_ID'),
        'production' => (bool) env('APNS_PRODUCTION', false),
    ],

    /*
    | The iOS sound must be a file bundled in the app, ≤ 30 seconds. Keep the name
    | in sync with `ios/Runner/arrival.caf`.
    */
    'ios_sound' => env('PUSH_IOS_SOUND', 'arrival.caf'),

];
