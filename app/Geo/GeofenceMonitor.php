<?php

namespace App\Geo;

use App\Models\GeofenceEvent;
use App\Models\Place;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;

/**
 * Applies a reported position to the tenant's places: records enter/exit events
 * and returns the store-specific shopping lists for anything now in range.
 */
class GeofenceMonitor
{
    public function __construct(
        private readonly GeofenceEvaluator $evaluator,
        private readonly GeofenceProvider $provider,
    ) {}

    /**
     * @return array{
     *     transitions: array<int, GeofenceTransition>,
     *     shopping_lists: array<int, array{place: Place, requests: Collection<int, ServiceRequest>}>
     * }
     */
    public function report(User $user, GeoPoint $position): array
    {
        $places = Place::query()->orderBy('name')->get();

        $definitions = $places->map(fn (Place $place): array => [
            'place_id' => (int) $place->id,
            'point' => $place->point(),
            'radius' => (int) $place->radius_meters,
        ])->all();

        $transitions = $this->evaluator->evaluate($definitions, $position, $this->previouslyInside($user, $places));

        foreach ($transitions as $transition) {
            GeofenceEvent::create([
                'tenant_id' => app(TenantContext::class)->id(),
                'place_id' => $transition->placeId,
                'user_id' => $user->getKey(),
                'event' => $transition->type,
                'occurred_at' => now(),
            ]);

            $this->provider->notify($transition);
        }

        return [
            'transitions' => $transitions,
            'shopping_lists' => $this->shoppingLists($places, $definitions, $position),
        ];
    }

    /**
     * @param  Collection<int, Place>  $places
     * @param  array<int, array{place_id: int, point: GeoPoint, radius: int}>  $definitions
     * @return array<int, array{place: Place, requests: Collection<int, ServiceRequest>}>
     */
    private function shoppingLists(Collection $places, array $definitions, GeoPoint $position): array
    {
        $lists = [];

        foreach ($definitions as $definition) {
            if (! $this->evaluator->isWithin($definition['point'], $definition['radius'], $position)) {
                continue;
            }

            $place = $places->firstWhere('id', $definition['place_id']);

            if ($place === null) {
                continue;
            }

            $lists[] = [
                'place' => $place,
                'requests' => ServiceRequest::query()
                    ->where('place_id', $place->id)
                    ->whereIn('status', ['pending', 'accepted', 'in_progress'])
                    ->orderBy('due_at')
                    ->get(),
            ];
        }

        return $lists;
    }

    /**
     * @param  Collection<int, Place>  $places
     * @return array<int, bool> place_id => was inside
     */
    private function previouslyInside(User $user, Collection $places): array
    {
        $latest = GeofenceEvent::query()
            ->where('user_id', $user->getKey())
            ->whereIn('place_id', $places->pluck('id'))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get()
            ->unique('place_id');

        return $latest
            ->mapWithKeys(fn (GeofenceEvent $event): array => [
                (int) $event->place_id => $event->event->value === 'enter',
            ])
            ->all();
    }
}
