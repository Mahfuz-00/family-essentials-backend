<?php

namespace Tests\Feature\Tenancy;

use App\Models\DeviceToken;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class RoleTierTest extends TestCase
{
    use CreatesTenants;

    public function test_a_co_admin_can_invite_but_cannot_change_roles_or_remove_members(): void
    {
        $tenant = $this->createTenant('Household A');
        $coAdmin = $this->createMember($tenant, 'co_admin');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($coAdmin);

        $this->withToken($token)
            ->postJson('/api/v1/tenant/invitations', ['email' => 'guest@example.com', 'role' => 'junior'])
            ->assertCreated();

        $this->withToken($token)
            ->patchJson("/api/v1/tenant/members/{$member->id}", ['role' => 'member'])
            ->assertStatus(403);

        $this->withToken($token)
            ->deleteJson("/api/v1/tenant/members/{$member->id}")
            ->assertStatus(403);
    }

    public function test_the_primary_admin_can_grant_the_new_roles(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($admin);

        $this->withToken($token)
            ->patchJson("/api/v1/tenant/members/{$member->id}", ['role' => 'co_admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'co_admin');

        $this->withToken($token)
            ->patchJson("/api/v1/tenant/members/{$member->id}", ['role' => 'junior'])
            ->assertOk()
            ->assertJsonPath('data.role', 'junior');
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($admin))
            ->patchJson("/api/v1/tenant/members/{$member->id}", ['role' => 'superuser'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_a_junior_can_raise_a_request_but_cannot_dispatch_one(): void
    {
        $tenant = $this->createTenant('Household A');
        $junior = $this->createMember($tenant, 'junior');
        $other = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($junior);

        $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Snacks for school'])
            ->assertCreated();

        $request = $this->createRequest($tenant, $junior);

        $this->withToken($token)
            ->postJson("/api/v1/requests/{$request->id}/assign", ['assignee_id' => $other->id])
            ->assertStatus(403);
    }

    public function test_a_junior_can_read_places_but_not_manage_them(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $junior = $this->createMember($tenant, 'junior');
        $token = $this->tokenFor($junior);

        $placeId = $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/places', [
                'name' => 'Whole Foods', 'latitude' => 23.81, 'longitude' => 90.41,
            ])
            ->assertCreated()
            ->json('data.id');

        // The shopping list depends on reading places.
        $this->withToken($token)->getJson('/api/v1/places')->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->postJson('/api/v1/places', ['name' => 'Sneaky', 'latitude' => 23.8, 'longitude' => 90.4])
            ->assertStatus(403);

        $this->withToken($token)
            ->patchJson("/api/v1/places/{$placeId}", ['radius_meters' => 900])
            ->assertStatus(403);

        $this->withToken($token)->deleteJson("/api/v1/places/{$placeId}")->assertStatus(403);
    }

    public function test_member_telemetry_reports_paired_devices_and_open_workload(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $member = $this->createMember($tenant, 'member');

        $this->withBypass(fn () => DeviceToken::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'platform' => 'ios',
            'token' => 'device-token-abc',
            'last_used_at' => now(),
        ]));

        $this->createRequest($tenant, $admin, ['assignee_id' => $member->id]);

        $payload = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/tenant/members')
            ->assertOk()
            ->json('data');

        $row = collect($payload)->firstWhere('user_id', $member->id);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['open_assignments']);
        $this->assertSame('ios', $row['devices'][0]['platform']);
    }

    public function test_voice_quota_reports_the_configured_bounds(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/voice-calls/quota')
            ->assertOk()
            ->assertJsonStructure(['used_today', 'daily_budget', 'max_attempts_per_request', 'cooldown_minutes'])
            ->assertJsonPath('daily_budget', (int) config('voice.daily_budget'));
    }
}
