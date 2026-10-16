<?php

namespace Tests\Integration\Surfaces;

use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The API has two client surfaces: /api for the React dashboard and /mobile for
 * the Flutter app. They share controllers but not credentials.
 */
class ClientSurfaceTest extends TestCase
{
    use CreatesTenants;

    public function test_the_mobile_surface_mints_a_mobile_token_and_serves_the_board(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $token = $this->postJson('/mobile/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
            'device_name' => 'flutter',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->getJson('/mobile/v1/requests/board')
            ->assertOk()
            ->assertJsonStructure(['counts', 'overdue', 'due_today']);
    }

    public function test_a_mobile_token_is_rejected_by_the_dashboard_surface(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $token = $this->postJson('/mobile/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/requests/board')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_a_dashboard_token_is_rejected_by_the_mobile_surface(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/requests/board')->assertOk();
        $this->withToken($token)->getJson('/mobile/v1/requests/board')->assertForbidden();
    }

    public function test_the_mobile_surface_does_not_expose_the_landlord_console(): void
    {
        $platform = $this->createPlatformAdmin();

        $token = $this->postJson('/mobile/v1/auth/login', [
            'email' => $platform->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        // Not registered on the mobile surface at all.
        $this->withToken($token)->getJson('/mobile/v1/platform/tenants')->assertNotFound();
        $this->withToken($token)->getJson('/mobile/v1/platform/audit-logs')->assertNotFound();
    }

    public function test_unauthenticated_endpoints_exist_on_both_surfaces(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'x'])
            ->assertStatus(422);

        $this->postJson('/mobile/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'x'])
            ->assertStatus(422);
    }
}
