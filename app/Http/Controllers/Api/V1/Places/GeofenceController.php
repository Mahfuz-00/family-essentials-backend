<?php

namespace App\Http\Controllers\Api\V1\Places;

use App\Geo\GeoPoint;
use App\Geo\GeofenceMonitor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Places\EvaluateGeofenceRequest;
use App\Http\Resources\GeofenceEventResource;
use App\Http\Resources\PlaceResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\GeofenceEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeofenceController extends Controller
{
    /**
     * Report a device position: returns any enter/exit transitions plus the
     * store-specific shopping lists for places now in range.
     */
    public function evaluate(EvaluateGeofenceRequest $request, GeofenceMonitor $monitor): JsonResponse
    {
        $position = new GeoPoint($request->float('latitude'), $request->float('longitude'));

        $result = $monitor->report($request->user(), $position);

        return response()->json([
            'transitions' => collect($result['transitions'])->map(fn ($transition): array => [
                'place_id' => $transition->placeId,
                'event' => $transition->type->value,
                'distance_meters' => round($transition->distanceMeters, 1),
            ])->values(),
            'shopping_lists' => collect($result['shopping_lists'])->map(fn (array $list): array => [
                'place' => new PlaceResource($list['place']),
                'requests' => ServiceRequestResource::collection($list['requests']),
            ])->values(),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $events = GeofenceEvent::query()
            ->with(['place:id,name', 'user:id,name'])
            ->latest('occurred_at')
            ->limit(50)
            ->get();

        return GeofenceEventResource::collection($events)->response();
    }
}
