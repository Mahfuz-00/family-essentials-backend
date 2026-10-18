<?php

namespace App\Geo;

use App\Enums\GeofenceEventType;
use App\Models\GeofenceEvent;
use App\Models\LocationNotification;
use App\Models\Place;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;

/**
 * Applies a reported position to the tenant's places: records enter/exit events,
 * returns the store-specific shopping lists for anything now in range, and tells
 * the member about their own work waiting where they have just arrived.
 */
class GeofenceMonitor
{
    public function __construct(
        private readonly GeofenceEvaluator $evaluator,
        private readonly GeofenceProvider $provider,
        private readonly LocationNotifier $notifier,
    ) {}

    /**
     * @return array{
     *     transitions: array<int, GeofenceTransition>,
     *     shopping_lists: array<int, array{place: Place, requests: Collection<int, ServiceRequest>}>,
     *     location_notifications: array<int, LocationNotification>
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

        $arrivals = [];

        foreach ($transitions as $transition) {
            $event = GeofenceEvent::create([
                'tenant_id' => app(TenantContext::class)->id(),
                'place_id' => $transition->placeId,
                'user_id' => $user->getKey(),
                'event' => $transition->type,
                'occurred_at' => now(),
            ]);

            $this->provider->notify($transition);

            // Arriving is what a member hears about; leaving is recorded but
            // silent. Only an entry transition can be an arrival.
            if ($transition->type === GeofenceEventType::Enter) {
                $place = $places->firstWhere('id', $transition->placeId);

                if ($place !== null) {
                    $arrivals[] = $this->notifier->onEnter($user, $place, $event);
                }
            }
        }

        return [
            'transitions' => $transitions,
            'shopping_lists' => $this->shoppingLists($places, $definitions, $position),
            'location_notifications' => $arrivals,
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
        // A dwell nobody closed is not evidence of being here now. A client that
        // stops reporting never writes the matching 'exit', so without this
        // cutoff an entry from days ago would keep the member "inside" and their
        // next visit would produce no transition — and therefore no arrival.
        $staleBefore = now()->subHours((int) config('location_notifications.stale_dwell_hours'));

        $latest = GeofenceEvent::query()
            ->where('user_id', $user->getKey())
            ->whereIn('place_id', $places->pluck('id'))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get()
            ->unique('place_id');

        return $latest
            ->mapWithKeys(fn (GeofenceEvent $event): array => [
                (int) $event->place_id => $event->event->value === 'enter'
                    && $event->occurred_at !== null
                    && $event->occurred_at->gte($staleBefore),
            ])
            ->all();
    }
}
