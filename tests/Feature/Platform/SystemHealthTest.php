<?php

namespace Tests\Feature\Platform;

use App\Models\HealthSample;
use App\Models\Incident;
use App\Ops\HealthReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use CreatesTenants;

    public function test_the_report_measures_every_check_it_claims(): void
    {
        $platform = $this->createPlatformAdmin();

        $checks = collect(
            $this->withToken($this->tokenFor($platform))
                ->getJson('/api/v1/platform/health')
                ->assertOk()
                ->json('report.checks')
        )->keyBy('key');

        foreach (['database', 'migrations', 'queue', 'scheduler', 'cache', 'storage', 'runtime'] as $key) {
            $this->assertTrue($checks->has($key), "the report is missing the [{$key}] check");
            $this->assertContains($checks[$key]['status'], ['ok', 'warn', 'fail']);
            $this->assertNotEmpty($checks[$key]['message']);
        }

        // Real numbers, not placeholders.
        $this->assertIsInt($checks['database']['value']);
        $this->assertSame('ms', $checks['database']['unit']);
        $this->assertArrayHasKey('php', $checks['runtime']['details']);
    }

    public function test_the_overall_status_is_the_worst_individual_check(): void
    {
        $this->assertSame('ok', HealthReport::rollUp([
            ['status' => 'ok'],
            ['status' => 'ok'],
        ]));

        $this->assertSame('warn', HealthReport::rollUp([
            ['status' => 'ok'],
            ['status' => 'warn'],
        ]));

        $this->assertSame('fail', HealthReport::rollUp([
            ['status' => 'warn'],
            ['status' => 'fail'],
        ]));
    }

    public function test_the_scheduler_check_notices_a_missing_heartbeat(): void
    {
        Cache::forget((string) config('ops.heartbeat_key'));

        $platform = $this->createPlatformAdmin();

        $checks = collect(
            $this->withToken($this->tokenFor($platform))
                ->getJson('/api/v1/platform/health')
                ->assertOk()
                ->json('report.checks')
        )->keyBy('key');

        $this->assertSame('warn', $checks['scheduler']['status']);
        $this->assertStringContainsString('No heartbeat', $checks['scheduler']['message']);
    }

    public function test_the_scheduler_check_reports_the_heartbeat_age(): void
    {
        Cache::put((string) config('ops.heartbeat_key'), Carbon::now()->subMinutes(10), now()->addHour());

        $platform = $this->createPlatformAdmin();

        $checks = collect(
            $this->withToken($this->tokenFor($platform))
                ->getJson('/api/v1/platform/health')
                ->assertOk()
                ->json('report.checks')
        )->keyBy('key');

        // 10 minutes is past the warn threshold (5) but not the fail threshold (30).
        $this->assertSame('warn', $checks['scheduler']['status']);
        $this->assertSame(10, $checks['scheduler']['value']);
    }

    public function test_uptime_is_measured_from_samples_and_is_null_without_any(): void
    {
        $platform = $this->createPlatformAdmin();
        $token = $this->tokenFor($platform);

        // No samples yet: an invented 100% would be a lie.
        $this->withToken($token)
            ->getJson('/api/v1/platform/health')
            ->assertOk()
            ->assertJsonPath('history.uptime_percent', null);

        foreach (['ok', 'ok', 'warn', 'fail'] as $status) {
            HealthSample::create(['checked_at' => now()->subMinutes(5), 'status' => $status]);
        }

        $this->withToken($token)
            ->getJson('/api/v1/platform/health')
            ->assertOk()
            // 2 of 4 samples ok. (PHP encodes a whole float as an int.)
            ->assertJsonPath('history.uptime_percent', 50)
            ->assertJsonCount(4, 'history.samples');
    }

    public function test_the_probe_command_records_a_sample_and_prunes_old_ones(): void
    {
        HealthSample::create(['checked_at' => now()->subDays(90), 'status' => 'ok']);

        $this->artisan('ops:health-probe')->assertSuccessful();

        $this->assertSame(1, HealthSample::query()->count());
        $this->assertTrue(HealthSample::query()->first()->checked_at->isToday());
    }

    public function test_the_heartbeat_command_stamps_the_scheduler(): void
    {
        Cache::forget((string) config('ops.heartbeat_key'));

        $this->artisan('ops:heartbeat')->assertSuccessful();

        $this->assertNotNull(Cache::get((string) config('ops.heartbeat_key')));
    }

    public function test_an_operator_can_open_and_resolve_an_incident(): void
    {
        $platform = $this->createPlatformAdmin();
        $token = $this->tokenFor($platform);

        $id = $this->withToken($token)
            ->postJson('/api/v1/platform/incidents', [
                'title' => 'Queue worker restarted',
                'severity' => 'major',
                'body' => 'Redis connection dropped for 3 minutes.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'investigating')
            ->assertJsonPath('data.severity', 'major')
            ->assertJsonPath('data.author.name', $platform->name)
            ->json('data.id');

        $this->assertDatabaseHas('audit_events', [
            'action' => 'ops.incident.opened',
            'level' => 'warning',
        ]);

        $this->withToken($token)
            ->patchJson("/api/v1/platform/incidents/{$id}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->assertNotNull(Incident::query()->findOrFail($id)->resolved_at);

        $this->assertDatabaseHas('audit_events', ['action' => 'ops.incident.updated']);
    }

    public function test_reopening_an_incident_clears_its_resolution(): void
    {
        $platform = $this->createPlatformAdmin();
        $token = $this->tokenFor($platform);

        $id = $this->withToken($token)
            ->postJson('/api/v1/platform/incidents', ['title' => 'Flaky sync', 'severity' => 'minor'])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->patchJson("/api/v1/platform/incidents/{$id}", ['status' => 'resolved'])
            ->assertOk();

        $this->withToken($token)
            ->patchJson("/api/v1/platform/incidents/{$id}", ['status' => 'monitoring'])
            ->assertOk();

        $this->assertNull(Incident::query()->findOrFail($id)->resolved_at);
    }

    public function test_an_incident_needs_a_title_and_a_known_severity(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/incidents', ['severity' => 'apocalyptic'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'severity']);
    }

    public function test_the_ops_surface_is_closed_to_households(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $token = $this->tokenFor($admin);

        $this->withToken($token)->getJson('/api/v1/platform/health')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/platform/incidents')->assertForbidden();
        $this->withToken($token)
            ->postJson('/api/v1/platform/incidents', ['title' => 'x', 'severity' => 'minor'])
            ->assertForbidden();
    }

    public function test_retrying_an_unknown_failed_job_is_a_clean_404(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/health/failed-jobs/999999/retry')
            ->assertStatus(404);
    }
}
