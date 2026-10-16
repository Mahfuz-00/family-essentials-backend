<?php

namespace Tests\Unit;

use App\Models\TenantUser;
use App\Tenancy\RoleCapabilities;
use Tests\TestCase;

/**
 * Unit scope: the role helpers on TenantUser and the declared capability matrix
 * in config/roles.php must agree. They are written in two places for readability
 * (a model method reads better than a config lookup at a call site), so this test
 * is what stops them drifting apart.
 */
class RoleCapabilityConsistencyTest extends TestCase
{
    public function test_the_model_helpers_match_the_declared_capability_matrix(): void
    {
        foreach (array_keys(RoleCapabilities::roles()) as $role) {
            $membership = new TenantUser(['role' => $role]);

            $this->assertSame(
                RoleCapabilities::allows('invite_members', $role),
                $membership->isAdmin(),
                "isAdmin() disagrees with the invite_members capability for [{$role}]"
            );

            $this->assertSame(
                RoleCapabilities::allows('manage_roles', $role),
                $membership->canManageMembers(),
                "canManageMembers() disagrees with the manage_roles capability for [{$role}]"
            );

            $this->assertSame(
                $role === 'junior',
                $membership->isJunior(),
                "[{$role}] is not classified correctly as junior"
            );
        }
    }

    public function test_a_junior_cannot_dispatch_but_everyone_can_complete_their_own(): void
    {
        $this->assertFalse(RoleCapabilities::allows('dispatch_requests', 'junior'));

        foreach (['tenant_admin', 'co_admin', 'member', 'junior'] as $role) {
            $this->assertTrue(
                RoleCapabilities::allows('complete_own_requests', $role),
                "[{$role}] should be able to complete its own requests"
            );
        }
    }

    public function test_only_a_tenant_admin_may_manage_roles(): void
    {
        $this->assertTrue(RoleCapabilities::allows('manage_roles', 'tenant_admin'));
        $this->assertFalse(RoleCapabilities::allows('manage_roles', 'co_admin'));
        $this->assertFalse(RoleCapabilities::allows('manage_roles', 'member'));
        $this->assertFalse(RoleCapabilities::allows('manage_roles', 'junior'));
    }

    public function test_an_unknown_or_missing_role_is_denied_everything(): void
    {
        foreach (array_keys(RoleCapabilities::capabilities()) as $capability) {
            $this->assertFalse(RoleCapabilities::allows($capability, null), "[{$capability}] allowed a null role");
            $this->assertFalse(RoleCapabilities::allows($capability, 'superuser'), "[{$capability}] allowed an unknown role");
        }
    }

    public function test_the_matrix_covers_every_role_for_every_capability(): void
    {
        $roles = array_keys(RoleCapabilities::roles());
        $matrix = RoleCapabilities::matrix();

        $this->assertCount(count(RoleCapabilities::capabilities()), $matrix);

        foreach ($matrix as $row) {
            $this->assertSame($roles, array_keys($row['access']), "row [{$row['key']}] does not cover every role");
        }
    }

    public function test_privileged_roles_are_exactly_the_admin_roles(): void
    {
        $this->assertSame(['tenant_admin', 'co_admin'], RoleCapabilities::privilegedRoles());

        foreach (['member', 'junior'] as $role) {
            $this->assertFalse(RoleCapabilities::isPrivilegedRole($role));
        }
    }
}
