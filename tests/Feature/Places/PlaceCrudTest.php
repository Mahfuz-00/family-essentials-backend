<?php

namespace Tests\Feature\Places;

use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PlaceCrudTest extends TestCase
{
    use CreatesTenants;

    public function test_a_member_can_create_list_update_and_delete_a_place(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $id = $this->withToken($token)
            ->postJson('/api/v1/places', [
                'name' => 'Whole Foods',
                'address' => '12 Main Street',
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'radius_meters' => 300,
                'category' => 'grocery',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Whole Foods')
            ->assertJsonPath('data.category', 'grocery')
            ->json('data.id');

        $this->withToken($token)
            ->getJson('/api/v1/places')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Whole Foods');

        $this->withToken($token)
            ->patchJson("/api/v1/places/{$id}", ['radius_meters' => 500])
            ->assertOk()
            ->assertJsonPath('data.radius_meters', 500);

        $this->withToken($token)->deleteJson("/api/v1/places/{$id}")->assertOk();

        $this->withBypass(fn () => $this->assertDatabaseCount('places', 0));
    }

    public function test_place_coordinates_and_radius_are_validated(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/places', [
                'name' => 'Bad place',
                'latitude' => 120,
                'longitude' => 200,
                'radius_meters' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude', 'radius_meters']);
    }

    public function test_a_place_can_be_filtered_by_category(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $this->withToken($token)->postJson('/api/v1/places', [
            'name' => 'CVS', 'latitude' => 23.81, 'longitude' => 90.41, 'category' => 'pharmacy',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/places', [
            'name' => 'Whole Foods', 'latitude' => 23.82, 'longitude' => 90.42, 'category' => 'grocery',
        ])->assertCreated();

        $this->withToken($token)
            ->getJson('/api/v1/places?category=pharmacy')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'CVS');
    }
}
