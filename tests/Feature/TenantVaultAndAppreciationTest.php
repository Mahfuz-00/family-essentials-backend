<?php

namespace Tests\Feature;

use App\Models\Essential;
use App\Models\Place;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class TenantVaultAndAppreciationTest extends TestCase
{
    use CreatesTenants;

    public function test_tenant_export_returns_complete_household_vault_json(): void
    {
        $tenant = $this->createTenant('Miller Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $this->createPlace($tenant, 'Costco Wholesale');

        $this->withBypass(function () use ($tenant, $user) {
            Essential::create([
                'tenant_id' => $tenant->id,
                'name' => 'Paper Towels',
                'category' => 'Household',
                'default_store' => 'Costco Wholesale',
                'stock_level' => 3,
                'threshold' => 1,
                'cadence_days' => 14,
                'created_by' => $user->id,
            ]);

            $this->createRequest($tenant, $user, ['title' => 'Pick up allergy meds']);
        });

        $response = $this->withToken($this->tokenFor($user))
            ->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson('/api/v1/tenant/export');

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="household-vault-'.$tenant->slug.'.json"');
        $response->assertJsonPath('data.format', 'family_essentials_vault_v1');
        $response->assertJsonPath('data.tenant.name', 'Miller Household');
        $response->assertJsonCount(1, 'data.members');
        $response->assertJsonCount(1, 'data.places');
        $response->assertJsonCount(1, 'data.pantry_essentials');
        $response->assertJsonCount(1, 'data.requests');
    }

    public function test_thank_endpoint_records_appreciation_for_errand(): void
    {
        $tenant = $this->createTenant('Gomez Household');
        $alex = $this->createMember($tenant, 'member', ['name' => 'Alex']);
        $jordan = $this->createMember($tenant, 'member', ['name' => 'Jordan']);

        $request = $this->createRequest($tenant, $alex, [
            'assignee_id' => $jordan->id,
            'title' => 'Pick up organic whole milk',
            'status' => 'completed',
        ]);

        $response = $this->withToken($this->tokenFor($alex))
            ->withHeader('X-Tenant-ID', $tenant->id)
            ->postJson("/api/v1/requests/{$request->id}/thank");

        $response->assertOk();
        $response->assertJsonPath('message', 'Appreciation sent to Jordan! 🙌');

        $this->withBypass(function () use ($request) {
            $this->assertStringContainsString('Alex sent appreciation', $request->fresh()->notes);
        });
    }

    public function test_pantry_low_stock_can_create_errand_directly(): void
    {
        $tenant = $this->createTenant('Lee Household');
        $user = $this->createMember($tenant, 'member');

        $essential = $this->withBypass(function () use ($tenant, $user) {
            return Essential::create([
                'tenant_id' => $tenant->id,
                'name' => 'Olive Oil Extra Virgin',
                'category' => 'Pantry',
                'default_store' => 'Trader Joe\'s',
                'stock_level' => 0,
                'threshold' => 1,
                'cadence_days' => 30,
                'pack_size' => '1 bottle',
                'created_by' => $user->id,
            ]);
        });

        $response = $this->withToken($this->tokenFor($user))
            ->withHeader('X-Tenant-ID', $tenant->id)
            ->postJson("/api/v1/essentials/{$essential->id}/create-errand");

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Restock Olive Oil Extra Virgin');
        $response->assertJsonPath('data.store_name', 'Trader Joe\'s');
        $response->assertJsonPath('data.priority', 'high');

        $this->withBypass(function () use ($tenant) {
            $this->assertDatabaseHas('requests', [
                'tenant_id' => $tenant->id,
                'title' => 'Restock Olive Oil Extra Virgin',
                'store_name' => 'Trader Joe\'s',
            ]);
        });
    }
}
