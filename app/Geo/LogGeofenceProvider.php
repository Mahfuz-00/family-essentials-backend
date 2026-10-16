<?php

namespace App\Geo;

use Illuminate\Support\Facades\Log;

class LogGeofenceProvider implements GeofenceProvider
{
    public function notify(GeofenceTransition $transition): void
    {
        Log::info('Geofence transition', [
            'place_id' => $transition->placeId,
            'event' => $transition->type->value,
            'distance_meters' => round($transition->distanceMeters, 1),
        ]);
    }
}
