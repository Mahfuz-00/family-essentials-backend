<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Push Driver
    |--------------------------------------------------------------------------
    |
    | Phase 4 ships a "log" driver that records the outgoing payload. A real
    | provider (FCM for Android / APNs for iOS) is wired up behind the same
    | PushSender contract once credentials are available.
    |
    */

    'driver' => env('PUSH_DRIVER', 'log'),

];
