<?php

namespace Tests\Feature\Tenancy;

use App\Models\TenantInvitation;
use App\Models\User;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use CreatesTenants;

    public function test_a_tenant_admin_can_invite_and_revoke_members(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $token = $this->tokenFor($admin);

        $invitationId = $this->withToken($token)
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'invitee@example.com',
                'role' => 'member',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'invitee@example.com')
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->withToken($token)
            ->getJson('/api/v1/tenant/invitations')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'invitee@example.com');

        $this->withToken($token)
            ->deleteJson("/api/v1/tenant/invitations/{$invitationId}")
            ->assertOk();
    }

    public function test_an_invited_user_can_accept_and_join_the_household(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $invitation = $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'joiner@example.com',
                'role' => 'member',
            ])
            ->assertCreated();

        $token = $this->withBypass(
            fn (): string => TenantInvitation::withoutGlobalScope('tenant')
                ->findOrFail($invitation->json('data.id'))
                ->token
        );

        $joiner = User::factory()->create(['email' => 'joiner@example.com']);

        $this->withToken($this->tokenFor($joiner))
            ->postJson('/api/v1/auth/invitations/accept', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('tenant.name', 'Household A');

        $this->withBypass(function () use ($tenant, $joiner): void {
            $this->assertDatabaseHas('tenant_users', [
                'tenant_id' => $tenant->id,
                'user_id' => $joiner->id,
                'role' => 'member',
            ]);
        });

        $this->assertSame($tenant->id, $joiner->fresh()->active_tenant_id);
    }

    public function test_an_invitation_for_a_different_email_is_rejected(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $invitation = $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/tenant/invitations', [
                'email' => 'intended@example.com',
                'role' => 'member',
            ])
            ->assertCreated();

        $token = $this->withBypass(
            fn (): string => TenantInvitation::withoutGlobalScope('tenant')
                ->findOrFail($invitation->json('data.id'))
                ->token
        );

        $stranger = User::factory()->create(['email' => 'stranger@example.com']);

        $this->withToken($this->tokenFor($stranger))
            ->postJson('/api/v1/auth/invitations/accept', ['token' => $token])
            ->assertStatus(403);
    }

    public function test_a_tenant_admin_can_change_roles_and_remove_members(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($admin);

        $this->withToken($token)
            ->patchJson("/api/v1/tenant/members/{$member->id}", ['role' => 'tenant_admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'tenant_admin');

        $this->withToken($token)
            ->deleteJson("/api/v1/tenant/members/{$member->id}")
            ->assertOk();
    }

    public function test_a_regular_member_cannot_manage_the_household(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $this->withToken($token)
            ->postJson('/api/v1/tenant/invitations', ['email' => 'x@example.com', 'role' => 'member'])
            ->assertStatus(403);

        $this->withToken($token)
            ->getJson('/api/v1/tenant/invitations')
            ->assertStatus(403);
    }
}
