<?php

namespace App\Geo;

/**
 * The seam the native geolocation plugin plugs into. Phase 5 ships a log driver.
 */
interface GeofenceProvider
{
    public function notify(GeofenceTransition $transition): void;
}
