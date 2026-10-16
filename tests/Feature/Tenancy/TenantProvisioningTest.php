<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use CreatesTenants;

    public function test_a_platform_admin_can_provision_suspend_and_activate_tenants(): void
    {
        $platform = $this->createPlatformAdmin();
        $token = $this->tokenFor($platform);

        $created = $this->withToken($token)
            ->postJson('/api/v1/platform/tenants', ['name' => 'New Household'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Household')
            ->assertJsonPath('data.status', 'trial');

        $tenantId = $created->json('data.id');

        $this->withToken($token)
            ->getJson('/api/v1/platform/tenants')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'status']]]);

        $this->withToken($token)
            ->postJson("/api/v1/platform/tenants/{$tenantId}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->withToken($token)
            ->postJson("/api/v1/platform/tenants/{$tenantId}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->withToken($token)
            ->getJson('/api/v1/platform/metrics')
            ->assertOk()
            ->assertJsonStructure(['total_tenants', 'by_status', 'by_tier', 'active_members']);
    }

    public function test_platform_endpoints_reject_non_platform_users(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/platform/tenants')
            ->assertStatus(403);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/platform/metrics')
            ->assertStatus(403);
    }

    public function test_platform_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/platform/tenants')->assertUnauthorized();
    }
}
