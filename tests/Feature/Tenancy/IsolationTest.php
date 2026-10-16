<?php

namespace Tests\Feature\Tenancy;

use App\Models\TenantInvitation;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class IsolationTest extends TestCase
{
    use CreatesTenants;

    public function test_a_tenant_admin_only_sees_their_own_members(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin', ['email' => 'a-admin@example.com']);
        $this->createMember($tenantA, 'member', ['email' => 'a-member@example.com']);

        $tenantB = $this->createTenant('Household B');
        $this->createMember($tenantB, 'tenant_admin', ['email' => 'b-admin@example.com']);

        $response = $this->withToken($this->tokenFor($adminA))->getJson('/api/v1/tenant/members');

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');

        $this->assertTrue($emails->contains('a-admin@example.com'));
        $this->assertTrue($emails->contains('a-member@example.com'));
        $this->assertFalse($emails->contains('b-admin@example.com'), 'Tenant B leaked into Tenant A.');
    }

    public function test_a_tenant_cannot_manage_members_of_another_tenant(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');

        $tenantB = $this->createTenant('Household B');
        $foreignMember = $this->createMember($tenantB, 'member');

        $this->withToken($this->tokenFor($adminA))
            ->patchJson("/api/v1/tenant/members/{$foreignMember->id}", ['role' => 'member'])
            ->assertNotFound();

        $this->withToken($this->tokenFor($adminA))
            ->deleteJson("/api/v1/tenant/members/{$foreignMember->id}")
            ->assertNotFound();
    }

    public function test_a_tenant_cannot_revoke_another_tenants_invitation(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');

        $tenantB = $this->createTenant('Household B');

        $foreignInvitation = $this->withBypass(fn () => TenantInvitation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantB->id,
            'email' => 'b-invitee@example.com',
            'role' => 'member',
            'token' => str_repeat('b', 64),
            'expires_at' => now()->addDays(7),
        ]));

        $this->withToken($this->tokenFor($adminA))
            ->deleteJson("/api/v1/tenant/invitations/{$foreignInvitation->id}")
            ->assertNotFound();
    }

    public function test_a_tenant_cannot_read_another_tenants_invitations(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');

        $tenantB = $this->createTenant('Household B');
        $this->withBypass(fn () => TenantInvitation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantB->id,
            'email' => 'b-secret@example.com',
            'role' => 'member',
            'token' => str_repeat('c', 64),
            'expires_at' => now()->addDays(7),
        ]));

        $response = $this->withToken($this->tokenFor($adminA))->getJson('/api/v1/tenant/invitations');

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertFalse($emails->contains('b-secret@example.com'));
    }
}
