<?php

namespace Tests\Integration\Reminders;

use App\Models\DeviceToken;
use App\Models\NotificationLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class NotificationIsolationTest extends TestCase
{
    use CreatesTenants;

    public function test_rls_denies_the_notification_tables_without_a_tenant_context(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withBypass(function () use ($tenant, $member): void {
            DeviceToken::create([
                'tenant_id' => $tenant->id,
                'user_id' => $member->id,
                'platform' => 'android',
                'token' => 'rls-check',
            ]);

            NotificationLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => $member->id,
                'channel' => 'push',
                'event' => 'reminder.due',
                'status' => 'sent',
            ]);
        });

        $connection = DB::connection('pgsql');
        $connection->statement("select set_config('app.current_tenant', '', false)");
        $connection->statement("select set_config('app.bypass_rls', 'off', false)");

        $this->assertSame(0, $connection->table('device_tokens')->count());
        $this->assertSame(0, $connection->table('notification_logs')->count());
        $this->assertSame(0, $connection->table('reminders')->count());
    }

    public function test_one_tenant_cannot_see_another_tenants_device_tokens(): void
    {
        $tenantA = $this->createTenant('Household A');
        $memberA = $this->createMember($tenantA, 'member');

        $tenantB = $this->createTenant('Household B');
        $memberB = $this->createMember($tenantB, 'member');

        $this->withBypass(fn () => DeviceToken::create([
            'tenant_id' => $tenantB->id,
            'user_id' => $memberB->id,
            'platform' => 'ios',
            'token' => 'tenant-b-device',
        ]));

        $this->withToken($this->tokenFor($memberA))
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withBypass(fn () => $this->assertSame(1, DeviceToken::withoutGlobalScope('tenant')->count()));
    }

    public function test_a_tenant_admin_can_set_household_quiet_hours(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($admin))
            ->patchJson('/api/v1/tenant', [
                'settings' => ['quiet_hours' => ['enabled' => true, 'start' => '21:30', 'end' => '06:30']],
            ])
            ->assertOk();

        $this->withBypass(function () use ($tenant): void {
            $fresh = Tenant::withoutGlobalScope('tenant')->findOrFail($tenant->id);

            $this->assertTrue($fresh->settings['quiet_hours']['enabled']);
            $this->assertSame('21:30', $fresh->settings['quiet_hours']['start']);
        });
    }

    public function test_quiet_hours_are_validated(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($admin))
            ->patchJson('/api/v1/tenant', [
                'settings' => ['quiet_hours' => ['enabled' => true, 'start' => '9pm']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.quiet_hours.start']);
    }
}
