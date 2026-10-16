<?php

namespace App\Http\Controllers\Api\V1\Places;

use App\Http\Controllers\Controller;
use App\Http\Requests\Places\StorePlaceRequest;
use App\Http\Requests\Places\UpdatePlaceRequest;
use App\Http\Resources\PlaceResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Place;
use App\Models\ServiceRequest;
use App\Plans\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $places = Place::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->withCount(['requests' => fn ($q) => $q->whereIn('status', ['pending', 'accepted', 'in_progress'])])
            ->orderBy('name')
            ->get();

        return PlaceResource::collection($places)->response();
    }

    public function store(StorePlaceRequest $request): JsonResponse
    {
        $this->authorize('create', Place::class);

        // The plan's place cap, enforced where places are actually created.
        app(QuotaService::class)->assertCanAddPlace();

        $place = Place::create([
            'name' => $request->string('name'),
            'address' => $request->input('address'),
            'latitude' => $request->float('latitude'),
            'longitude' => $request->float('longitude'),
            'radius_meters' => $request->integer('radius_meters', 300),
            'category' => $request->input('category'),
            'created_by' => $request->user()->getKey(),
        ]);

        return (new PlaceResource($place))->response()->setStatusCode(201);
    }

    public function update(UpdatePlaceRequest $request, Place $place): JsonResponse
    {
        $this->authorize('update', $place);

        $place->update($request->validated());

        return (new PlaceResource($place))->response();
    }

    public function destroy(Place $place): JsonResponse
    {
        $this->authorize('delete', $place);

        $place->delete();

        return response()->json(['message' => 'Place removed.']);
    }

    /**
     * The store-specific shopping list for a saved place.
     */
    public function requests(Place $place): JsonResponse
    {
        $requests = ServiceRequest::query()
            ->where('place_id', $place->id)
            ->whereIn('status', ['pending', 'accepted', 'in_progress'])
            ->with(['item', 'requester', 'assignee'])
            ->orderBy('due_at')
            ->get();

        return response()->json([
            'place' => new PlaceResource($place),
            'data' => ServiceRequestResource::collection($requests),
        ]);
    }
}
