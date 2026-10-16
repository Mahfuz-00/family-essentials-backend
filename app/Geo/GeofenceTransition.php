<?php

namespace App\Geo;

use App\Enums\GeofenceEventType;

final readonly class GeofenceTransition
{
    public function __construct(
        public int $placeId,
        public GeofenceEventType $type,
        public float $distanceMeters,
    ) {}
}
