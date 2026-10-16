<?php

namespace App\Geo;

use App\Enums\GeofenceEventType;

/**
 * Pure geofence maths - no framework, no database, fully unit-testable.
 * Household-scale radii make haversine the right call (no PostGIS needed).
 */
class GeofenceEvaluator
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    public function distanceMeters(GeoPoint $from, GeoPoint $to): float
    {
        $lat1 = deg2rad($from->latitude);
        $lat2 = deg2rad($to->latitude);
        $deltaLat = deg2rad($to->latitude - $from->latitude);
        $deltaLng = deg2rad($to->longitude - $from->longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($a)));
    }

    public function isWithin(GeoPoint $center, int $radiusMeters, GeoPoint $point): bool
    {
        return $this->distanceMeters($center, $point) <= $radiusMeters;
    }

    /**
     * @param  array<int, array{place_id: int, point: GeoPoint, radius: int}>  $places
     * @param  array<int, bool>  $previouslyInside  place_id => was inside
     * @return array<int, GeofenceTransition>
     */
    public function evaluate(array $places, GeoPoint $current, array $previouslyInside = []): array
    {
        $transitions = [];

        foreach ($places as $place) {
            $distance = $this->distanceMeters($place['point'], $current);
            $inside = $distance <= $place['radius'];
            $wasInside = $previouslyInside[$place['place_id']] ?? false;

            if ($inside && ! $wasInside) {
                $transitions[] = new GeofenceTransition($place['place_id'], GeofenceEventType::Enter, $distance);
            } elseif (! $inside && $wasInside) {
                $transitions[] = new GeofenceTransition($place['place_id'], GeofenceEventType::Exit, $distance);
            }
        }

        return $transitions;
    }
}
