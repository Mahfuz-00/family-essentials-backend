<?php

namespace Tests\Feature\Platform;

use App\Models\Backup;
use App\Ops\Backups\BackupResult;
use App\Ops\Backups\BackupRunner;
use App\Ops\Backups\BackupService;
use App\Ops\Backups\PostgresDumpRunner;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * A dumper that is honest in the same way the real one is: it writes an actual
 * file and reports facts measured from it.
 */
class FakeDumpRunner implements BackupRunner
{
    public function __construct(
        private readonly bool $available = true,
        private readonly bool $explode = false,
    ) {}

    public function driver(): string
    {
        return 'fake';
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function requirement(): string
    {
        return 'Install the fake dumper.';
    }

    public function run(string $filename, string $disk): BackupResult
    {
        if ($this->explode) {
            throw new RuntimeException('the dump process died');
        }

        Storage::disk($disk)->put($filename, str_repeat('PGDMP', 512));

        $path = (string) Storage::disk($disk)->path($filename);

        return new BackupResult(
            filename: $filename,
            disk: $disk,
            sizeBytes: (int) filesize($path),
            checksum: (string) hash_file('sha256', $path),
        );
    }
}

class BackupTest extends TestCase
{
    use CreatesTenants;

    private function useFakeDumper(bool $available = true, bool $explode = false): void
    {
        $this->app->instance(BackupRunner::class, new FakeDumpRunner($available, $explode));
    }

    public function test_a_completed_run_records_a_real_file_and_its_checksum(): void
    {
        $this->useFakeDumper();

        $backup = app(BackupService::class)->run('manual');

        $this->assertSame(Backup::COMPLETED, $backup->status);
        $this->assertGreaterThan(0, $backup->size_bytes);
        $this->assertSame(64, strlen((string) $backup->checksum));
        $this->assertTrue(Storage::disk($backup->disk)->exists($backup->filename));

        // Verification is what turns a registered file into a restorable one.
        $verified = app(BackupService::class)->verify($backup);

        $this->assertNull($verified->verify_error);
        $this->assertNotNull($verified->verified_at);
        $this->assertTrue($verified->isRestorable());
    }

    public function test_a_run_without_a_dump_tool_is_skipped_not_faked(): void
    {
        $this->useFakeDumper(available: false);

        $backup = app(BackupService::class)->run('scheduled');

        $this->assertSame(Backup::SKIPPED, $backup->status);
        $this->assertNull($backup->checksum);
        $this->assertStringContainsString('Install the fake dumper', (string) $backup->error);
        $this->assertFalse($backup->isRestorable());

        // And no file was invented for it.
        $this->assertFalse(Storage::disk($backup->disk)->exists($backup->filename));
    }

    public function test_a_failed_dump_is_recorded_with_its_reason(): void
    {
        $this->useFakeDumper(explode: true);

        $backup = app(BackupService::class)->run('manual');

        $this->assertSame(Backup::FAILED, $backup->status);
        $this->assertStringContainsString('the dump process died', (string) $backup->error);
        $this->assertNotNull($backup->finished_at);
    }

    public function test_verification_notices_a_file_that_changed_underneath_us(): void
    {
        $this->useFakeDumper();

        $backup = app(BackupService::class)->verify(app(BackupService::class)->run('manual'));

        $this->assertTrue($backup->isRestorable());

        // Something corrupted the dump after it was written.
        Storage::disk($backup->disk)->put($backup->filename, 'tampered');

        $recheck = app(BackupService::class)->verify($backup);

        $this->assertNotNull($recheck->verify_error);
        $this->assertStringContainsString('checksum does not match', (string) $recheck->verify_error);
        $this->assertTrue($recheck->hasDrifted());
        $this->assertFalse($recheck->isRestorable());
    }

    public function test_pruning_removes_dumps_past_retention_and_leaves_the_file_behind_nowhere(): void
    {
        $this->useFakeDumper();

        $old = app(BackupService::class)->run('scheduled');
        $old->update(['started_at' => now()->subDays(60)]);

        $fresh = app(BackupService::class)->run('scheduled');

        $removed = app(BackupService::class)->prune();

        $this->assertSame(1, $removed);
        $this->assertNull(Backup::query()->find($old->id));
        $this->assertFalse(Storage::disk($old->disk)->exists($old->filename));
        $this->assertNotNull(Backup::query()->find($fresh->id));
    }

    public function test_a_run_that_was_interrupted_is_reaped_rather_than_left_running(): void
    {
        $this->useFakeDumper();

        $stale = Backup::create([
            'filename' => 'backups/interrupted.dump',
            'disk' => 'local',
            'driver' => 'pg_dump',
            'type' => 'manual',
            'status' => 'running',
            'started_at' => now()->subHours(3),
        ]);

        $inFlight = Backup::create([
            'filename' => 'backups/current.dump',
            'disk' => 'local',
            'driver' => 'pg_dump',
            'type' => 'manual',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $reaped = app(BackupService::class)->reapAbandoned();

        $this->assertSame(1, $reaped);
        $this->assertSame(Backup::FAILED, $stale->refresh()->status);
        $this->assertNotNull($stale->finished_at);
        $this->assertStringContainsString('interrupted', (string) $stale->error);

        // A run that is genuinely under way is left alone.
        $this->assertSame('running', $inFlight->refresh()->status);
    }

    public function test_readiness_states_the_gaps_instead_of_asserting_readiness(): void
    {
        $this->useFakeDumper(available: false);

        $readiness = app(BackupService::class)->readiness();

        $this->assertFalse($readiness['ready']);
        $this->assertContains('Install the fake dumper.', $readiness['gaps']);
        $this->assertContains('No successful backup has ever been recorded.', $readiness['gaps']);
        $this->assertSame((int) config('backup.rpo_hours'), $readiness['rpo_hours']);
        $this->assertNull($readiness['latest_backup']);
    }

    public function test_readiness_turns_green_once_a_backup_exists_and_is_verified(): void
    {
        $this->useFakeDumper();

        app(BackupService::class)->verify(app(BackupService::class)->run('manual'));

        $readiness = app(BackupService::class)->readiness();

        $this->assertSame([], $readiness['gaps']);
        $this->assertTrue($readiness['ready']);
        $this->assertTrue($readiness['latest_backup']['restorable']);
        $this->assertSame(0, $readiness['latest_backup']['age_hours']);
    }

    public function test_readiness_warns_when_the_dump_connection_is_the_runtime_role(): void
    {
        // The runtime role is subject to FORCE ROW LEVEL SECURITY: a dump taken as
        // it would silently contain zero rows from every tenant table.
        config(['backup.connection' => config('database.default')]);
        $this->useFakeDumper();

        $readiness = app(BackupService::class)->readiness();

        $this->assertFalse($readiness['connection_is_owner']);
        $this->assertTrue(
            collect($readiness['gaps'])->contains(fn (string $gap) => str_contains($gap, 'zero rows')),
            'readiness should warn that RLS would make the dump empty'
        );
    }

    public function test_the_console_exposes_readiness_the_registry_and_the_runbook(): void
    {
        $this->useFakeDumper();
        $platform = $this->createPlatformAdmin();

        app(BackupService::class)->verify(app(BackupService::class)->run('manual'));

        $payload = $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/backups')
            ->assertOk()
            ->json();

        $this->assertTrue($payload['readiness']['ready']);
        $this->assertCount(1, $payload['backups']);
        $this->assertTrue($payload['backups'][0]['restorable']);
        $this->assertNotEmpty($payload['runbook']['steps']);
        $this->assertStringContainsString(
            'pg_restore',
            collect($payload['runbook']['steps'])->pluck('command')->implode(' ')
        );
    }

    public function test_taking_a_backup_through_the_api_is_audited(): void
    {
        $this->useFakeDumper();
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/backups')
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.restorable', true);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'ops.backup.created',
            'actor_email' => $platform->email,
            'level' => 'info',
        ]);
    }

    public function test_a_skipped_run_reports_no_content_without_pretending(): void
    {
        $this->useFakeDumper(available: false);
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/backups')
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'skipped')
            ->assertJsonPath('data.restorable', false);
    }

    public function test_verifying_and_deleting_a_backup(): void
    {
        $this->useFakeDumper();
        $platform = $this->createPlatformAdmin();

        $id = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/backups')
            ->assertCreated()
            ->json('data.id');

        $backup = Backup::query()->findOrFail($id);

        $this->withToken($this->tokenFor($platform))
            ->postJson("/api/v1/platform/backups/{$id}/verify")
            ->assertOk()
            ->assertJsonPath('data.restorable', true);

        $this->withToken($this->tokenFor($platform))
            ->deleteJson("/api/v1/platform/backups/{$id}")
            ->assertOk();

        $this->assertNull(Backup::query()->find($id));
        $this->assertFalse(Storage::disk($backup->disk)->exists($backup->filename));
        $this->assertDatabaseHas('audit_events', ['action' => 'ops.backup.deleted']);
    }

    public function test_downloading_a_backup_is_streamed_and_audited(): void
    {
        $this->useFakeDumper();
        $platform = $this->createPlatformAdmin();

        $id = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/backups')
            ->assertCreated()
            ->json('data.id');

        $filename = basename((string) Backup::query()->findOrFail($id)->filename);

        $this->withToken($this->tokenFor($platform))
            ->get("/api/v1/platform/backups/{$id}/download")
            ->assertOk()
            ->assertDownload($filename);

        // Exfiltrating the whole database is worth a log line.
        $this->assertDatabaseHas('audit_events', ['action' => 'ops.backup.downloaded']);
    }

    public function test_the_backup_surface_is_closed_to_households(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/platform/backups')
            ->assertForbidden();

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/platform/backups')
            ->assertForbidden();
    }

    /**
     * The one test that shells out. It proves the real path end to end: the dump
     * is taken as the owner connection, a file appears, and its checksum matches.
     *
     * Opt-in, because a child process started from inside PHPUnit on Windows can
     * block the run. Enable it with BACKUP_REAL_DUMP_TEST=1 and confirm with:
     *
     *     php artisan backups:run --type=manual
     */
    public function test_a_real_pg_dump_produces_a_restorable_file(): void
    {
        if (env('BACKUP_REAL_DUMP_TEST') !== '1') {
            $this->markTestSkipped('Set BACKUP_REAL_DUMP_TEST=1 to exercise the real pg_dump path.');
        }

        $runner = new PostgresDumpRunner;

        if (! $runner->isAvailable()) {
            $this->markTestSkipped('pg_dump is not on PATH here.');
        }

        $this->app->instance(BackupRunner::class, $runner);

        // Keep the suite honest about time: a wedged dump must not hang the run.
        config(['backup.timeout_seconds' => 120]);

        $backup = app(BackupService::class)->run('manual');

        $this->assertSame(Backup::COMPLETED, $backup->status, (string) $backup->error);
        $this->assertGreaterThan(1024, (int) $backup->size_bytes, 'a real dump is not a few bytes');
        $this->assertSame('pg_dump', $backup->driver);

        $verified = app(BackupService::class)->verify($backup);

        $this->assertNull($verified->verify_error);
        $this->assertTrue($verified->isRestorable());

        // The dump must be taken as the owner: the runtime role is FORCE RLS'd and
        // would export zero rows from every tenant table.
        $this->assertNotSame(
            config('database.default'),
            config('backup.connection'),
            'backups must not be taken as the runtime role'
        );

        Storage::disk($backup->disk)->delete($backup->filename);
    }
}
