<?php

namespace Tests\Feature\Places;

use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class StoreFilterTest extends TestCase
{
    use CreatesTenants;

    public function test_requests_can_be_filtered_by_saved_place(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $placeId = $this->withToken($token)
            ->postJson('/api/v1/places', ['name' => 'Whole Foods', 'latitude' => 23.81, 'longitude' => 90.41])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Milk', 'place_id' => $placeId])
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Batteries', 'store_name' => 'Corner Shop'])
            ->assertCreated();

        $this->withToken($token)
            ->getJson("/api/v1/requests?place_id={$placeId}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Milk');
    }

    public function test_a_free_text_store_name_still_works_without_a_place(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Batteries', 'store_name' => 'Corner Shop'])
            ->assertCreated()
            ->assertJsonPath('data.store_name', 'Corner Shop')
            ->assertJsonPath('data.place_id', null);

        $this->withToken($token)
            ->getJson('/api/v1/requests?store=Corner')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.store_name', 'Corner Shop');
    }

    public function test_the_store_shopping_list_endpoint_returns_only_open_requests_for_that_place(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $placeId = $this->withToken($token)
            ->postJson('/api/v1/places', ['name' => 'Whole Foods', 'latitude' => 23.81, 'longitude' => 90.41])
            ->assertCreated()
            ->json('data.id');

        $openId = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Milk', 'place_id' => $placeId])
            ->json('data.id');

        $doneId = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Bread', 'place_id' => $placeId])
            ->json('data.id');

        $this->withToken($token)->postJson("/api/v1/requests/{$doneId}/accept")->assertOk();
        $this->withToken($token)->postJson("/api/v1/requests/{$doneId}/complete")->assertOk();

        $this->withToken($token)
            ->getJson("/api/v1/places/{$placeId}/requests")
            ->assertOk()
            ->assertJsonPath('place.name', 'Whole Foods')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $openId);
    }

    public function test_assigning_a_request_to_another_tenants_place_is_rejected(): void
    {
        $tenantA = $this->createTenant('Household A');
        $memberA = $this->createMember($tenantA, 'member');

        $tenantB = $this->createTenant('Household B');
        $memberB = $this->createMember($tenantB, 'member');

        $foreignPlaceId = $this->withToken($this->tokenFor($memberB))
            ->postJson('/api/v1/places', ['name' => 'B Mart', 'latitude' => 23.9, 'longitude' => 90.9])
            ->json('data.id');

        $this->withToken($this->tokenFor($memberA))
            ->postJson('/api/v1/requests', ['title' => 'Milk', 'place_id' => $foreignPlaceId])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['place_id']);
    }
}
