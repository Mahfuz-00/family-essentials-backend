<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Geofence Driver
    |--------------------------------------------------------------------------
    |
    | Phase 5 ships a "log" driver that records transitions server-side. The
    | real on-device background monitoring (geolocator + a foreground service on
    | Android, background location mode on iOS) plugs in behind the same
    | GeofenceProvider contract once it can be validated on hardware.
    |
    */

    'driver' => env('GEOFENCE_DRIVER', 'log'),

];
