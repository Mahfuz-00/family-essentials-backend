<?php

namespace Tests\Feature\Platform;

use App\Models\AuditEvent;
use App\Models\Tenant;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The single-tenant half of the landlord console: read one household, change it,
 * and export the list.
 *
 * The list, provisioning, suspension and bulk actions are covered in
 * `Tests\Integration\Platform\AuditLogTest`; this file is the show/update/export
 * group that had no coverage at all.
 */
class TenantConsoleTest extends TestCase
{
    use CreatesTenants;

    public function test_a_tenant_is_shown_with_its_member_count(): void
    {
        $tenant = $this->createTenant('Household A');
        $this->createMember($tenant, 'tenant_admin');
        $this->createMember($tenant, 'member');
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->getJson("/api/v1/platform/tenants/{$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.name', 'Household A')
            ->assertJsonPath('data.slug', $tenant->slug)
            ->assertJsonPath('data.member_count', 2)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'slug', 'status', 'subscription_tier', 'settings', 'created_at'],
            ]);
    }

    public function test_the_console_reads_across_households(): void
    {
        $mine = $this->createTenant('Household A');
        $theirs = $this->createTenant('Household B');

        // A platform admin who belongs to one household still sees the other:
        // the console is cross-tenant by design, through the RLS bypass.
        $platform = $this->createMember($mine, 'tenant_admin');

        $platform->forceFill(['is_platform_admin' => true])->save();

        $this->withToken($this->tokenFor($platform))
            ->getJson("/api/v1/platform/tenants/{$theirs->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Household B');
    }

    public function test_a_tenant_that_does_not_exist_is_a_404(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/tenants/999999')
            ->assertNotFound();
    }

    public function test_updating_a_tenant_is_recorded_in_the_audit_ledger(): void
    {
        $tenant = $this->createTenant('Household A');
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->patchJson("/api/v1/platform/tenants/{$tenant->id}", [
                'name' => 'The Renamed Household',
                'subscription_tier' => 'standard',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'The Renamed Household')
            ->assertJsonPath('data.subscription_tier', 'standard');

        $this->withBypass(function () use ($tenant): void {
            $stored = Tenant::query()->findOrFail($tenant->id);

            $this->assertSame('The Renamed Household', $stored->name);
            $this->assertSame('standard', $stored->subscription_tier);
        });

        $event = AuditEvent::query()->where('action', 'tenant.updated')->sole();

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertSame('info', $event->level);
        $this->assertSame('Household A', $event->payload['before']['name']);
        $this->assertSame('The Renamed Household', $event->payload['after']['name']);
        $this->assertSame('pilot', $event->payload['before']['subscription_tier']);
        $this->assertSame('standard', $event->payload['after']['subscription_tier']);
    }

    public function test_an_update_must_use_a_known_tier(): void
    {
        $tenant = $this->createTenant('Household A');
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->patchJson("/api/v1/platform/tenants/{$tenant->id}", ['subscription_tier' => 'platinum'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subscription_tier']);
    }

    public function test_a_household_admin_is_refused_by_show_update_and_export(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $token = $this->tokenFor($admin);

        $this->withToken($token)
            ->getJson("/api/v1/platform/tenants/{$tenant->id}")
            ->assertForbidden();

        $this->withToken($token)
            ->patchJson("/api/v1/platform/tenants/{$tenant->id}", ['name' => 'Mine now'])
            ->assertForbidden();

        $this->withToken($token)
            ->getJson('/api/v1/platform/tenants/export')
            ->assertForbidden();

        $this->withBypass(function () use ($tenant): void {
            $this->assertSame('Household A', Tenant::query()->findOrFail($tenant->id)->name);
        });
    }

    public function test_the_tenant_list_exports_as_csv(): void
    {
        $this->createTenant('Household A');
        $this->createTenant('Household B');
        $platform = $this->createPlatformAdmin();

        $response = $this->withToken($this->tokenFor($platform))
            ->get('/api/v1/platform/tenants/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('id,name,slug,status,subscription_tier,members,created_at', $csv);
        $this->assertStringContainsString('Household A', $csv);
        $this->assertStringContainsString('Household B', $csv);
    }

    public function test_the_export_honours_the_same_filters_as_the_list(): void
    {
        $household = $this->createTenant('Household A');
        $platform = $this->createPlatformAdmin();

        // Give the two households different tiers, so the filter has something to
        // separate.
        $this->withToken($this->tokenFor($platform))
            ->patchJson("/api/v1/platform/tenants/{$household->id}", ['subscription_tier' => 'standard'])
            ->assertOk();

        $this->createTenant('Household B');

        $csv = $this->withToken($this->tokenFor($platform))
            ->get('/api/v1/platform/tenants/export?tier=standard')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Household A', $csv);
        $this->assertStringNotContainsString('Household B', $csv);
    }
}
