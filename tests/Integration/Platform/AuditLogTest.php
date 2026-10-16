<?php

namespace Tests\Integration\Platform;

use App\Models\AuditEvent;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use CreatesTenants;

    public function test_a_successful_login_is_recorded(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
            'device_name' => 'phpunit',
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'action' => 'auth.login',
            'actor_user_id' => $admin->id,
            'level' => 'info',
        ]);
    }

    public function test_a_failed_login_is_recorded_as_a_warning(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'auth.login_failed',
            'level' => 'warning',
            'actor_email' => 'nobody@example.com',
            'http_status' => 422,
        ]);
    }

    public function test_provisioning_and_suspending_a_tenant_is_recorded(): void
    {
        $platform = $this->createPlatformAdmin();
        $token = $this->tokenFor($platform);

        $tenantId = $this->withToken($token)
            ->postJson('/api/v1/platform/tenants', ['name' => 'Audited Household'])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('audit_events', [
            'action' => 'tenant.created',
            'tenant_id' => $tenantId,
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/platform/tenants/{$tenantId}/suspend")
            ->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'action' => 'tenant.suspended',
            'tenant_id' => $tenantId,
            'level' => 'warning',
        ]);
    }

    public function test_a_non_admin_hitting_platform_routes_is_recorded_as_critical(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/platform/tenants')
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'auth.platform_access_denied',
            'level' => 'critical',
            'actor_user_id' => $member->id,
        ]);
    }

    public function test_the_ledger_is_readable_only_by_platform_admins(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/platform/audit-logs')
            ->assertStatus(403);

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/audit-logs')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'trace_id', 'occurred_at', 'level', 'action', 'actor_email', 'source_ip']],
                'meta' => ['current_page', 'last_page', 'total'],
            ]);
    }

    public function test_the_ledger_can_be_filtered_by_level_and_action(): void
    {
        $platform = $this->createPlatformAdmin();
        $token = $this->tokenFor($platform);

        AuditEvent::create([
            'trace_id' => '01HZZZZZZZZZZZZZZZZZZZZZAA',
            'occurred_at' => now(),
            'level' => 'critical',
            'action' => 'rls.violation',
        ]);

        AuditEvent::create([
            'trace_id' => '01HZZZZZZZZZZZZZZZZZZZZZBB',
            'occurred_at' => now(),
            'level' => 'info',
            'action' => 'tenant.created',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/platform/audit-logs?level=critical')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'rls.violation');

        $this->withToken($token)
            ->getJson('/api/v1/platform/audit-logs?action=tenant.')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'tenant.created');
    }

    public function test_metrics_include_security_totals(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/metrics')
            ->assertOk()
            ->assertJsonStructure([
                'audit' => ['events_24h', 'critical_24h', 'warning_24h', 'denied_platform_access_24h', 'total'],
            ]);
    }

    public function test_the_ledger_exports_as_csv(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->get('/api/v1/platform/audit-logs/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_tenants_can_be_bulk_suspended_and_reactivated(): void
    {
        $a = $this->createTenant('Household A');
        $b = $this->createTenant('Household B');
        $platform = $this->createPlatformAdmin();
        $token = $this->tokenFor($platform);

        $this->withToken($token)
            ->postJson('/api/v1/platform/tenants/bulk/suspend', ['ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('affected', 2);

        $this->withBypass(function () use ($a, $b): void {
            $this->assertSame('suspended', $a->fresh()->status);
            $this->assertSame('suspended', $b->fresh()->status);
        });

        $this->assertDatabaseHas('audit_events', [
            'action' => 'tenant.bulk_suspended',
            'level' => 'warning',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/platform/tenants/bulk/activate', ['ids' => [$a->id]])
            ->assertOk()
            ->assertJsonPath('affected', 1);

        $this->withBypass(function () use ($a, $b): void {
            $this->assertSame('active', $a->fresh()->status);
            $this->assertSame('suspended', $b->fresh()->status);
        });
    }

    public function test_the_tenant_list_is_paginated_and_filterable_by_tier(): void
    {
        $this->createTenant('Household A');
        $platform = $this->createPlatformAdmin();
        $token = $this->tokenFor($platform);

        $this->withToken($token)
            ->getJson('/api/v1/platform/tenants?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
            ->assertJsonPath('meta.per_page', 1);

        $this->withToken($token)
            ->getJson('/api/v1/platform/tenants?tier=pilot')
            ->assertOk()
            ->assertJsonPath('data.0.subscription_tier', 'pilot');
    }
}
