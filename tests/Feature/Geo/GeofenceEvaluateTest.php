<?php

namespace Tests\Feature\Geo;

use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class GeofenceEvaluateTest extends TestCase
{
    use CreatesTenants;

    /**
     * @return array{0: int, 1: string, 2: int} [placeId, token, memberId]
     */
    private function placeWithShoppingList(): array
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $placeId = $this->withToken($token)
            ->postJson('/api/v1/places', [
                'name' => 'Whole Foods',
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'radius_meters' => 300,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Milk', 'place_id' => $placeId])
            ->assertCreated()
            ->assertJsonPath('data.place_id', $placeId)
            ->assertJsonPath('data.store_name', 'Whole Foods');

        return [$placeId, $token, (int) $member->id];
    }

    public function test_reporting_a_position_inside_a_place_records_an_enter_and_returns_the_shopping_list(): void
    {
        [$placeId, $token, $memberId] = $this->placeWithShoppingList();

        $this->withToken($token)
            ->postJson('/api/v1/geofences/evaluate', ['latitude' => 23.8105, 'longitude' => 90.4125])
            ->assertOk()
            ->assertJsonPath('transitions.0.place_id', $placeId)
            ->assertJsonPath('transitions.0.event', 'enter')
            ->assertJsonPath('shopping_lists.0.place.name', 'Whole Foods')
            ->assertJsonPath('shopping_lists.0.requests.0.title', 'Milk');

        $this->withBypass(function () use ($placeId, $memberId): void {
            $this->assertDatabaseHas('geofence_events', [
                'place_id' => $placeId,
                'user_id' => $memberId,
                'event' => 'enter',
            ]);
        });
    }

    public function test_staying_inside_produces_no_further_transition(): void
    {
        [$placeId, $token] = $this->placeWithShoppingList();

        $this->withToken($token)
            ->postJson('/api/v1/geofences/evaluate', ['latitude' => 23.8105, 'longitude' => 90.4125])
            ->assertOk()
            ->assertJsonCount(1, 'transitions');

        $this->withToken($token)
            ->postJson('/api/v1/geofences/evaluate', ['latitude' => 23.8105, 'longitude' => 90.4125])
            ->assertOk()
            ->assertJsonCount(0, 'transitions')
            ->assertJsonPath('shopping_lists.0.place.id', $placeId);
    }

    public function test_leaving_the_radius_records_an_exit(): void
    {
        [, $token] = $this->placeWithShoppingList();

        $this->withToken($token)
            ->postJson('/api/v1/geofences/evaluate', ['latitude' => 23.8105, 'longitude' => 90.4125])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/geofences/evaluate', ['latitude' => 23.8300, 'longitude' => 90.4125])
            ->assertOk()
            ->assertJsonPath('transitions.0.event', 'exit')
            ->assertJsonCount(0, 'shopping_lists');
    }

    public function test_the_geofence_event_log_lists_transitions(): void
    {
        [, $token] = $this->placeWithShoppingList();

        $this->withToken($token)
            ->postJson('/api/v1/geofences/evaluate', ['latitude' => 23.8105, 'longitude' => 90.4125])
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/geofence-events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'enter')
            ->assertJsonPath('data.0.place.name', 'Whole Foods');
    }

    public function test_the_position_report_is_validated(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/geofences/evaluate', ['latitude' => 200, 'longitude' => 500])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }
}
