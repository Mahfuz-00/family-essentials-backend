<?php

namespace Tests\Feature\Requests;

use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class RequestIsolationTest extends TestCase
{
    use CreatesTenants;

    public function test_a_tenant_cannot_read_another_tenants_request(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');

        $tenantB = $this->createTenant('Household B');
        $memberB = $this->createMember($tenantB, 'member');
        $foreign = $this->createRequest($tenantB, $memberB, ['title' => 'Secret of B']);

        $this->withToken($this->tokenFor($adminA))
            ->getJson("/api/v1/requests/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_a_tenant_cannot_act_on_another_tenants_request(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');

        $tenantB = $this->createTenant('Household B');
        $memberB = $this->createMember($tenantB, 'member');
        $foreign = $this->createRequest($tenantB, $memberB, ['title' => 'Secret of B']);

        $tokenA = $this->tokenFor($adminA);

        $this->withToken($tokenA)->postJson("/api/v1/requests/{$foreign->id}/complete")->assertNotFound();
        $this->withToken($tokenA)->postJson("/api/v1/requests/{$foreign->id}/cancel")->assertNotFound();
    }

    public function test_a_tenant_only_sees_its_own_requests_and_items(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');
        $this->createRequest($tenantA, $adminA, ['title' => 'A request']);
        $this->createCatalogItem($tenantA, 'A item');

        $tenantB = $this->createTenant('Household B');
        $memberB = $this->createMember($tenantB, 'member');
        $this->createRequest($tenantB, $memberB, ['title' => 'B request']);
        $this->createCatalogItem($tenantB, 'B item');

        $this->withToken($this->tokenFor($adminA))
            ->getJson('/api/v1/requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'A request');

        $this->withToken($this->tokenFor($adminA))
            ->getJson('/api/v1/items')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'A item');
    }

    public function test_rls_denies_workflow_tables_without_a_tenant_context(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $this->createCatalogItem($tenant, 'Milk');
        $this->createRequest($tenant, $member, ['title' => 'Milk']);

        $connection = DB::connection('pgsql');
        $connection->statement("select set_config('app.current_tenant', '', false)");
        $connection->statement("select set_config('app.bypass_rls', 'off', false)");

        $this->assertSame(0, $connection->table('items')->count());
        $this->assertSame(0, $connection->table('requests')->count());
        $this->assertSame(0, $connection->table('assignments')->count());
    }

    public function test_a_second_tenant_cannot_switch_into_the_other_tenants_requests(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');
        $this->createRequest($tenantA, $adminA, ['title' => 'A only']);

        $tenantB = $this->createTenant('Household B');
        $adminB = $this->createMember($tenantB, 'tenant_admin');

        $this->withToken($this->tokenFor($adminB))
            ->getJson('/api/v1/requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withBypass(fn () => $this->assertSame(1, ServiceRequest::query()->count()));
    }
}
