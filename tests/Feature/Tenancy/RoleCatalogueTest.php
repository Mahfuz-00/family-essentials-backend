<?php

namespace Tests\Feature\Tenancy;

use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The Roles & Permissions module's data source. The matrix is served from
 * config/roles.php — the same declaration the policies enforce — so the screen
 * cannot advertise an ability the API does not honour.
 */
class RoleCatalogueTest extends TestCase
{
    use CreatesTenants;

    public function test_it_serves_the_role_catalogue_with_real_member_counts(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $this->createMember($tenant, 'member');
        $this->createMember($tenant, 'junior');

        $payload = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/tenant/roles')
            ->assertOk()
            ->json();

        $this->assertTrue($payload['can_manage_roles']);
        $this->assertCount(4, $payload['roles']);

        $byKey = collect($payload['roles'])->keyBy('key');

        $this->assertSame(1, $byKey['tenant_admin']['member_count']);
        $this->assertSame(1, $byKey['member']['member_count']);
        $this->assertSame(1, $byKey['junior']['member_count']);
        $this->assertSame(0, $byKey['co_admin']['member_count']);
        $this->assertSame('Tenant admin', $byKey['tenant_admin']['label']);
    }

    public function test_the_matrix_covers_every_capability_for_every_role(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $payload = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/tenant/roles')
            ->assertOk()
            ->json();

        $roles = collect($payload['roles'])->pluck('key')->all();

        $this->assertNotEmpty($payload['capabilities']);

        foreach ($payload['capabilities'] as $capability) {
            $this->assertSame($roles, array_keys($capability['access']), "row [{$capability['key']}]");

            foreach ($capability['access'] as $allowed) {
                $this->assertIsBool($allowed);
            }
        }
    }

    public function test_the_matrix_matches_what_the_api_actually_enforces(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $matrix = collect(
            $this->withToken($this->tokenFor($admin))
                ->getJson('/api/v1/tenant/roles')
                ->assertOk()
                ->json('capabilities')
        )->keyBy('key');

        // The matrix says a co-admin cannot manage roles — and the endpoint agrees.
        $this->assertFalse($matrix['manage_roles']['access']['co_admin']);

        $coAdmin = $this->createMember($tenant, 'co_admin');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($coAdmin))
            ->patchJson("/api/v1/tenant/members/{$member->id}", ['role' => 'member'])
            ->assertStatus(403);
    }

    public function test_a_co_admin_sees_admin_roles_as_ungrantable(): void
    {
        $tenant = $this->createTenant('Household A');
        $coAdmin = $this->createMember($tenant, 'co_admin');

        $payload = $this->withToken($this->tokenFor($coAdmin))
            ->getJson('/api/v1/tenant/roles')
            ->assertOk()
            ->json();

        $byKey = collect($payload['roles'])->keyBy('key');

        $this->assertFalse($payload['can_manage_roles']);

        // They may still invite members, just not admins.
        $this->assertFalse($byKey['tenant_admin']['can_be_granted']);
        $this->assertFalse($byKey['co_admin']['can_be_granted']);
        $this->assertTrue($byKey['member']['can_be_granted']);
        $this->assertTrue($byKey['junior']['can_be_granted']);
    }

    public function test_any_household_member_may_read_the_matrix(): void
    {
        $tenant = $this->createTenant('Household A');
        $junior = $this->createMember($tenant, 'junior');

        $this->withToken($this->tokenFor($junior))
            ->getJson('/api/v1/tenant/roles')
            ->assertOk()
            ->assertJsonPath('can_manage_roles', false);
    }
}
