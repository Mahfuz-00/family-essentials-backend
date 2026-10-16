<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use CreatesTenants;

    public function test_registering_provisions_a_household_and_lists_it(): void
    {
        $registered = $this->postJson('/api/v1/auth/register', [
            'name' => 'New Owner',
            'email' => 'owner@example.com',
            'phone' => '+15551110000',
            'password' => 'Password!2345',
            'password_confirmation' => 'Password!2345',
            'household_name' => 'The Owner Household',
        ])->assertCreated();

        $token = $registered->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/tenants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.0.role', 'tenant_admin');
    }

    public function test_a_user_can_switch_their_active_tenant(): void
    {
        $tenantA = $this->createTenant('Household A');
        $tenantB = $this->createTenant('Household B');

        $user = User::factory()->create();
        $this->attachToTenant($tenantA, $user, 'tenant_admin');
        $this->attachToTenant($tenantB, $user, 'member');
        $user->forceFill(['active_tenant_id' => $tenantA->id])->save();

        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/v1/auth/tenants')->assertJsonCount(2, 'data');

        $this->withToken($token)
            ->postJson('/api/v1/auth/tenant/switch', ['tenant_id' => $tenantB->id])
            ->assertOk();

        $this->assertSame($tenantB->id, $user->fresh()->active_tenant_id);

        $this->withToken($token)
            ->getJson('/api/v1/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $tenantB->id);
    }

    public function test_a_user_cannot_switch_to_a_tenant_they_do_not_belong_to(): void
    {
        $tenantA = $this->createTenant('Household A');
        $foreign = $this->createTenant('Household B');

        $user = $this->createMember($tenantA, 'member');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/auth/tenant/switch', ['tenant_id' => $foreign->id])
            ->assertStatus(403);
    }

    public function test_a_platform_admin_can_override_the_tenant_via_header(): void
    {
        $platform = $this->createPlatformAdmin();

        $tenant = $this->createTenant('Household A');
        $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($platform))
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson('/api/v1/tenant')
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->id);
    }

    public function test_a_user_without_an_active_tenant_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/tenant/members')
            ->assertStatus(403);
    }
}
