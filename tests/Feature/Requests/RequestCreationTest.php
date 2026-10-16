<?php

namespace Tests\Feature\Requests;

use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class RequestCreationTest extends TestCase
{
    use CreatesTenants;

    public function test_a_member_can_create_a_free_text_request_and_grow_the_catalog(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $response = $this->withToken($this->tokenFor($member))->postJson('/api/v1/requests', [
            'title' => 'Organic Whole Milk',
            'quantity' => '2 litres',
            'priority' => 'high',
            'store_name' => 'Whole Foods',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Organic Whole Milk')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.quantity', '2 litres');

        $this->withBypass(function () use ($tenant): void {
            $this->assertDatabaseHas('items', ['tenant_id' => $tenant->id, 'name' => 'Organic Whole Milk']);
            $this->assertDatabaseHas('requests', ['tenant_id' => $tenant->id, 'title' => 'Organic Whole Milk']);
        });
    }

    public function test_a_free_text_request_links_to_an_existing_catalog_item(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $item = $this->createCatalogItem($tenant, 'Milk', ['category' => 'Dairy']);

        $response = $this->withToken($this->tokenFor($member))->postJson('/api/v1/requests', [
            'title' => 'milk',
        ]);

        $response->assertCreated()->assertJsonPath('data.item_id', $item->id);

        $this->withBypass(fn () => $this->assertDatabaseCount('items', 1));
    }

    public function test_a_request_can_be_created_from_a_catalog_item(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $item = $this->createCatalogItem($tenant, 'Olive Oil', [
            'category' => 'Pantry',
            'default_store' => 'Trader Joes',
        ]);

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/requests', ['item_id' => $item->id])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Olive Oil')
            ->assertJsonPath('data.store_name', 'Trader Joes')
            ->assertJsonPath('data.category', 'Pantry');
    }

    public function test_a_request_requires_a_title_or_a_catalog_item(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/requests', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_an_assignee_must_be_a_member_of_the_household(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $otherTenant = $this->createTenant('Household B');
        $outsider = $this->createMember($otherTenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/requests', ['title' => 'Milk', 'assignee_id' => $outsider->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assignee_id']);
    }

    public function test_catalog_items_can_be_listed_and_created(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $this->withToken($token)
            ->postJson('/api/v1/items', ['name' => 'Eggs', 'category' => 'Dairy'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Eggs');

        $this->withToken($token)
            ->getJson('/api/v1/items?search=Egg')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Eggs');
    }
}
