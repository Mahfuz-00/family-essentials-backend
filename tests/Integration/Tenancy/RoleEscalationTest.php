<?php

namespace Tests\Integration\Tenancy;

use App\Models\TenantInvitation;
use App\Models\TenantUser;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Integration scope: the invitation and role-change paths span middleware, the
 * policy layer, the controllers and the database, so the regressions live here.
 *
 * The privilege escalation these guard against was real: a co-admin could POST an
 * invitation with role=tenant_admin and, once accepted, have an existing
 * membership's role overwritten — promoting themselves to full admin.
 */
class RoleEscalationTest extends TestCase
{
    use CreatesTenants;

    public function test_a_co_admin_cannot_invite_a_tenant_admin(): void
    {
        $tenant = $this->createTenant('Household A');
        $coAdmin = $this->createMember($tenant, 'co_admin');

        $this->withToken($this->tokenFor($coAdmin))
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'climber@example.com',
                'role' => 'tenant_admin',
            ])
            ->assertStatus(403);

        $this->withBypass(fn () => $this->assertDatabaseCount('tenant_invitations', 0));
    }

    public function test_a_co_admin_cannot_invite_a_co_admin_either(): void
    {
        $tenant = $this->createTenant('Household A');
        $coAdmin = $this->createMember($tenant, 'co_admin');

        $this->withToken($this->tokenFor($coAdmin))
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'peer@example.com',
                'role' => 'co_admin',
            ])
            ->assertStatus(403);
    }

    public function test_a_co_admin_can_still_invite_a_member_or_junior(): void
    {
        $tenant = $this->createTenant('Household A');
        $coAdmin = $this->createMember($tenant, 'co_admin');
        $token = $this->tokenFor($coAdmin);

        $this->withToken($token)
            ->postJson('/api/v1/tenant/invitations', ['email' => 'new@example.com', 'role' => 'member'])
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/tenant/invitations', ['email' => 'teen@example.com', 'role' => 'junior'])
            ->assertCreated();
    }

    public function test_the_owner_can_still_grant_an_admin_role_by_invitation(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'partner@example.com',
                'role' => 'tenant_admin',
            ])
            ->assertCreated();
    }

    public function test_accepting_an_invitation_never_overwrites_an_existing_role(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $member = $this->createMember($tenant, 'member');

        // The owner invites the *existing member's own address* as a tenant admin.
        $invitationId = $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/tenant/invitations', [
                'email' => $member->email,
                'role' => 'tenant_admin',
            ])
            ->assertCreated()
            ->json('data.id');

        $invitationToken = $this->withBypass(
            fn (): string => TenantInvitation::withoutGlobalScope('tenant')
                ->findOrFail($invitationId)
                ->token
        );

        // Accepting must refuse, not promote.
        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/auth/invitations/accept', ['token' => $invitationToken])
            ->assertStatus(422);

        $this->withBypass(function () use ($tenant, $member): void {
            $this->assertDatabaseHas('tenant_users', [
                'tenant_id' => $tenant->id,
                'user_id' => $member->id,
                'role' => 'member',
            ]);
        });
    }

    public function test_an_owner_cannot_change_their_own_role(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($admin))
            ->patchJson("/api/v1/tenant/members/{$admin->id}", ['role' => 'member'])
            ->assertStatus(422);

        $this->withBypass(function () use ($tenant, $admin): void {
            $this->assertDatabaseHas('tenant_users', [
                'tenant_id' => $tenant->id,
                'user_id' => $admin->id,
                'role' => 'tenant_admin',
            ]);
        });
    }

    public function test_an_owner_cannot_remove_themselves(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/tenant/members/{$admin->id}")
            ->assertStatus(403);
    }

    public function test_a_junior_cannot_dispatch_by_creating_a_request_with_an_assignee(): void
    {
        $tenant = $this->createTenant('Household A');
        $junior = $this->createMember($tenant, 'junior');
        $other = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($junior))
            ->postJson('/api/v1/requests', [
                'title' => 'Take the bins out',
                'assignee_id' => $other->id,
            ])
            ->assertStatus(403);
    }

    public function test_a_member_can_still_assign_on_create(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $other = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/requests', [
                'title' => 'Collect the parcel',
                'assignee_id' => $other->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.assignee.id', $other->id);
    }

    public function test_a_request_with_a_null_item_id_and_no_title_is_rejected_not_crashed(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/requests', ['item_id' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }
}
