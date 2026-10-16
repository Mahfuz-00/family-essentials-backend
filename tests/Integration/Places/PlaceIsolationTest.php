<?php

namespace Tests\Integration\Places;

use App\Models\Place;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PlaceIsolationTest extends TestCase
{
    use CreatesTenants;

    public function test_a_tenant_cannot_update_or_delete_another_tenants_place(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');

        $tenantB = $this->createTenant('Household B');
        $memberB = $this->createMember($tenantB, 'member');

        $foreignPlaceId = $this->withToken($this->tokenFor($memberB))
            ->postJson('/api/v1/places', ['name' => 'B Mart', 'latitude' => 23.9, 'longitude' => 90.9])
            ->assertCreated()
            ->json('data.id');

        $tokenA = $this->tokenFor($adminA);

        $this->withToken($tokenA)->patchJson("/api/v1/places/{$foreignPlaceId}", ['name' => 'Hacked'])->assertNotFound();
        $this->withToken($tokenA)->deleteJson("/api/v1/places/{$foreignPlaceId}")->assertNotFound();
        $this->withToken($tokenA)->getJson("/api/v1/places/{$foreignPlaceId}/requests")->assertNotFound();
    }

    public function test_a_tenant_only_sees_its_own_places(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');
        $this->createPlace($tenantA, 'A Mart');

        $tenantB = $this->createTenant('Household B');
        $memberB = $this->createMember($tenantB, 'member');
        $this->createPlace($tenantB, 'B Mart');

        $this->withToken($this->tokenFor($adminA))
            ->getJson('/api/v1/places')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'A Mart');
    }

    public function test_rls_denies_the_location_tables_without_a_tenant_context(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/places', ['name' => 'Whole Foods', 'latitude' => 23.81, 'longitude' => 90.41])
            ->assertCreated();

        $connection = DB::connection('pgsql');
        $connection->statement("select set_config('app.current_tenant', '', false)");
        $connection->statement("select set_config('app.bypass_rls', 'off', false)");

        $this->assertSame(0, $connection->table('places')->count());
        $this->assertSame(0, $connection->table('geofence_events')->count());
    }

    private function createPlace(\App\Models\Tenant $tenant, string $name): Place
    {
        return $this->withBypass(function () use ($tenant, $name): Place {
            $place = new Place;
            $place->forceFill([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'latitude' => 23.81,
                'longitude' => 90.41,
            ])->save();

            return $place;
        });
    }
}
