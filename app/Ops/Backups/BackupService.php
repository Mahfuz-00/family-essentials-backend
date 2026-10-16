<?php

namespace App\Ops\Backups;

use App\Models\Backup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The backup lifecycle: take one, prove it is intact, prune the old ones, and
 * report whether any of that is actually possible here.
 *
 * The one rule: never record a run as successful unless a file exists and its
 * checksum was computed from what is on disk.
 */
class BackupService
{
    public function __construct(private readonly BackupRunner $runner) {}

    public function runner(): BackupRunner
    {
        return $this->runner;
    }

    public function run(string $type = 'manual', ?int $userId = null): Backup
    {
        // Clear up anything a killed process left behind before starting another.
        $this->reapAbandoned();

        $disk = (string) config('backup.disk');
        $started = Carbon::now();

        $backup = Backup::create([
            'filename' => $this->filename($type),
            'disk' => $disk,
            'driver' => $this->runner->driver(),
            'type' => $type,
            'status' => 'running',
            'started_at' => $started,
            'created_by' => $userId,
        ]);

        // No dump tool: record the run as skipped with the fix, rather than
        // pretending a backup exists.
        if (! $this->runner->isAvailable()) {
            $backup->update([
                'status' => Backup::SKIPPED,
                'finished_at' => Carbon::now(),
                'duration_seconds' => 0,
                'error' => $this->runner->requirement(),
            ]);

            return $backup->refresh();
        }

        try {
            $result = $this->runner->run($backup->filename, $disk);

            $backup->update([
                'filename' => $result->filename,
                'size_bytes' => $result->sizeBytes,
                'checksum' => $result->checksum,
                'status' => Backup::COMPLETED,
                'finished_at' => Carbon::now(),
                'duration_seconds' => (int) $started->diffInSeconds(Carbon::now()),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $backup->update([
                'status' => Backup::FAILED,
                'finished_at' => Carbon::now(),
                'duration_seconds' => (int) $started->diffInSeconds(Carbon::now()),
                'error' => $e->getMessage(),
            ]);
        }

        return $backup->refresh();
    }

    /**
     * Fail any run that was never finished.
     *
     * A killed process, a fatal error or a dropped worker leaves a row in
     * `running` with nothing behind it — and because pruning deliberately skips
     * running rows, it would sit there forever, reading as work in progress.
     * Anything past the dump timeout plus a minute is not still dumping.
     */
    public function reapAbandoned(): int
    {
        $cutoff = Carbon::now()->subSeconds((int) config('backup.timeout_seconds') + 60);

        return Backup::query()
            ->where('status', 'running')
            ->where('started_at', '<', $cutoff)
            ->update([
                'status' => Backup::FAILED,
                'finished_at' => Carbon::now(),
                'error' => 'The backup process was interrupted before it finished.',
            ]);
    }

    /**
     * Re-hash the file on disk and compare it with the checksum recorded when it
     * was written. This is what turns a registered file into a restorable one.
     */
    public function verify(Backup $backup): Backup
    {
        if ($backup->status !== Backup::COMPLETED || $backup->checksum === null) {
            $backup->update([
                'verified_at' => Carbon::now(),
                'verify_error' => 'There is no completed dump to verify.',
            ]);

            return $backup->refresh();
        }

        try {
            if (! Storage::disk($backup->disk)->exists($backup->filename)) {
                throw new \RuntimeException('The file is no longer on disk.');
            }

            $actual = (string) hash_file(
                'sha256',
                (string) Storage::disk($backup->disk)->path($backup->filename),
            );

            if (! hash_equals($backup->checksum, $actual)) {
                throw new \RuntimeException('The checksum does not match what was recorded.');
            }

            $backup->update(['verified_at' => Carbon::now(), 'verify_error' => null]);
        } catch (Throwable $e) {
            $backup->update(['verified_at' => Carbon::now(), 'verify_error' => $e->getMessage()]);
        }

        return $backup->refresh();
    }

    /**
     * Delete dumps past retention. Only rows whose file is genuinely gone (or
     * already absent) are removed, so the registry never outlives its evidence.
     */
    public function prune(): int
    {
        $cutoff = Carbon::now()->subDays((int) config('backup.retention_days'));
        $removed = 0;

        Backup::query()
            ->where('started_at', '<', $cutoff)
            ->whereNotIn('status', ['running'])
            ->get()
            ->each(function (Backup $backup) use (&$removed): void {
                try {
                    Storage::disk($backup->disk)->delete($backup->filename);
                } catch (Throwable) {
                    // A missing file is the outcome we want anyway.
                }

                $backup->delete();
                $removed++;
            });

        return $removed;
    }

    /**
     * The honest answer to "could we restore, and how much would we lose".
     *
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $connection = (string) config('backup.connection');
        $disk = (string) config('backup.disk');
        $directory = (string) config('backup.directory');

        $destinationWritable = rescue(
            fn (): bool => Storage::disk($disk)->exists($directory)
                && Storage::disk($disk)->makeDirectory($directory) === true,
            false,
            report: false,
        );

        // Dumping as the runtime role would export zero rows from every
        // FORCE-RLS table — a backup that restores to an empty database.
        $connectionIsOwner = $connection !== config('database.default');

        $latest = Backup::query()
            ->where('status', Backup::COMPLETED)
            ->orderByDesc('started_at')
            ->first();

        $rpoHours = (int) config('backup.rpo_hours');
        $ageHours = $latest?->started_at === null
            ? null
            : (int) $latest->started_at->diffInHours(Carbon::now());

        $gaps = [];

        if (! $this->runner->isAvailable()) {
            $gaps[] = $this->runner->requirement();
        }

        if (! $connectionIsOwner) {
            $gaps[] = "The backup connection [{$connection}] is the runtime role; row-level security would export zero rows. Point it at the owner role.";
        }

        if (! $destinationWritable) {
            $gaps[] = "The backup directory [{$directory}] on the [{$disk}] disk is not writable.";
        }

        if ($ageHours === null) {
            $gaps[] = 'No successful backup has ever been recorded.';
        } elseif ($ageHours > $rpoHours) {
            $gaps[] = "The newest backup is {$ageHours} h old, past the {$rpoHours} h recovery point objective.";
        }

        return [
            'ready' => $gaps === [],
            'gaps' => $gaps,
            'driver' => $this->runner->driver(),
            'dumper_available' => $this->runner->isAvailable(),
            'requirement' => $this->runner->requirement(),
            'connection' => $connection,
            'connection_is_owner' => $connectionIsOwner,
            'disk' => $disk,
            'directory' => $directory,
            'destination_writable' => $destinationWritable,
            'retention_days' => (int) config('backup.retention_days'),
            'rpo_hours' => $rpoHours,
            'rto_minutes' => (int) config('backup.rto_minutes'),
            'latest_backup' => $latest === null ? null : [
                'id' => $latest->id,
                'filename' => $latest->filename,
                'started_at' => $latest->started_at?->toIso8601String(),
                'age_hours' => $ageHours,
                'size_bytes' => $latest->size_bytes,
                'restorable' => $latest->isRestorable(),
            ],
            'completed_count' => Backup::query()->where('status', Backup::COMPLETED)->count(),
        ];
    }

    private function filename(string $type): string
    {
        // A short suffix as well as the timestamp: two runs inside the same second
        // (a manual one landing on the scheduled minute) must not collide.
        return sprintf(
            'family-essentials-%s-%s-%s.dump',
            $type,
            Carbon::now()->format('Ymd-His'),
            Str::lower(Str::random(4)),
        );
    }
}
