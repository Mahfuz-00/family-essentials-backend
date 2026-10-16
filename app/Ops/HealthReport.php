<?php

namespace App\Ops;

use App\Models\HealthSample;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Runs the operational checks that answer "is this system healthy, and if not,
 * where".
 *
 * Two rules govern everything here:
 *
 *  1. A check that cannot run reports `fail` with the reason. A green light for
 *     something unverified is worse than no check at all.
 *  2. Checks stay cheap — they run on a schedule as well as on demand, so none of
 *     them may scan a large table or call a remote service.
 */
class HealthReport
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /**
     * @return array{status: string, checked_at: string, checks: array<int, array<string, mixed>>}
     */
    public function run(): array
    {
        $checks = [
            $this->database(),
            $this->migrations(),
            $this->queue(),
            $this->scheduler(),
            $this->cache(),
            $this->storage(),
            $this->runtime(),
        ];

        return [
            'status' => self::rollUp($checks),
            'checked_at' => Carbon::now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * The overall status is the worst individual status. Averaging would hide the
     * one check that matters.
     *
     * @param  array<int, array<string, mixed>>  $checks
     */
    public static function rollUp(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array(self::FAIL, $statuses, true)) {
            return self::FAIL;
        }

        if (in_array(self::WARN, $statuses, true)) {
            return self::WARN;
        }

        return self::OK;
    }

    /**
     * Share of samples in the window that were ok. Null when there is nothing to
     * measure yet — an invented 100% would be a lie.
     */
    public function uptimePercent(int $hours = 24): ?float
    {
        $window = HealthSample::query()->where('checked_at', '>=', Carbon::now()->subHours($hours));

        $total = (clone $window)->count();

        if ($total === 0) {
            return null;
        }

        $ok = (clone $window)->where('status', self::OK)->count();

        return round($ok / $total * 100, 2);
    }

    /**
     * @return array{status: string, uptime_percent: float|null, samples: array<int, array<string, mixed>>}
     */
    public function history(int $hours = 24, int $limit = 96): array
    {
        $samples = HealthSample::query()
            ->where('checked_at', '>=', Carbon::now()->subHours($hours))
            ->orderBy('checked_at')
            ->limit($limit)
            ->get()
            ->map(fn (HealthSample $sample): array => [
                'checked_at' => $sample->checked_at?->toIso8601String(),
                'status' => $sample->status,
            ])
            ->all();

        return [
            'uptime_percent' => $this->uptimePercent($hours),
            'samples' => $samples,
        ];
    }

    // --------------------------------------------------------------- the checks

    /**
     * @return array<string, mixed>
     */
    private function database(): array
    {
        $started = microtime(true);

        try {
            DB::connection()->select('select 1');
        } catch (Throwable $e) {
            return self::check('database', 'Database', self::FAIL, 'Cannot connect: '.$e->getMessage());
        }

        $ms = (int) round((microtime(true) - $started) * 1000);
        $warn = (int) config('ops.thresholds.database_latency_warn_ms');
        $fail = (int) config('ops.thresholds.database_latency_fail_ms');

        $status = $ms >= $fail ? self::FAIL : ($ms >= $warn ? self::WARN : self::OK);

        return self::check(
            'database',
            'Database',
            $status,
            "Round trip {$ms} ms",
            $ms,
            'ms',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function migrations(): array
    {
        try {
            $migrator = app('migrator');
            $migrator->setConnection(config('database.default'));

            $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));
            $ran = $migrator->getRepository()->getRan();
            $pending = array_values(array_diff($files, $ran));
        } catch (Throwable $e) {
            return self::check('migrations', 'Schema', self::FAIL, 'Cannot read migrations: '.$e->getMessage());
        }

        $count = count($pending);

        return self::check(
            'migrations',
            'Schema',
            $count === 0 ? self::OK : self::WARN,
            $count === 0
                ? 'All migrations applied'
                : $count.' migration'.($count === 1 ? '' : 's').' pending — run migrate as the owner',
            $count,
            'pending',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        try {
            $backlog = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable $e) {
            return self::check('queue', 'Queue', self::FAIL, 'Cannot read the queue: '.$e->getMessage());
        }

        $warn = (int) config('ops.thresholds.queue_backlog_warn');
        $fail = (int) config('ops.thresholds.queue_backlog_fail');

        $status = $backlog >= $fail ? self::FAIL : ($backlog >= $warn ? self::WARN : self::OK);

        if ($failed > 0) {
            // A failed job is not a healthy signal, whatever the backlog says.
            $status = $status === self::OK ? self::WARN : $status;
        }

        $driver = (string) config('queue.default');

        return self::check(
            'queue',
            'Queue',
            $status,
            "{$backlog} queued · {$failed} failed ({$driver} driver)",
            $backlog,
            'queued',
            ['failed' => $failed, 'driver' => $driver],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduler(): array
    {
        $key = (string) config('ops.heartbeat_key');
        $last = Cache::get($key);

        if ($last === null) {
            return self::check(
                'scheduler',
                'Scheduler',
                self::WARN,
                'No heartbeat recorded yet — is schedule:run wired up?',
            );
        }

        $last = $last instanceof Carbon ? $last : Carbon::parse((string) $last);
        $ageMinutes = (int) $last->diffInMinutes(Carbon::now());

        $warn = (int) config('ops.thresholds.scheduler_warn_minutes');
        $fail = (int) config('ops.thresholds.scheduler_fail_minutes');

        $status = $ageMinutes >= $fail ? self::FAIL : ($ageMinutes >= $warn ? self::WARN : self::OK);

        return self::check(
            'scheduler',
            'Scheduler',
            $status,
            $ageMinutes <= 0 ? 'Heartbeat just now' : "Last heartbeat {$ageMinutes} min ago",
            $ageMinutes,
            'min',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cache(): array
    {
        $key = 'ops:cache-probe';

        try {
            $value = (string) microtime(true);
            Cache::put($key, $value, 60);
            $read = Cache::get($key);
            Cache::forget($key);
        } catch (Throwable $e) {
            return self::check('cache', 'Cache', self::FAIL, 'Cannot use the cache: '.$e->getMessage());
        }

        if ($read !== $value) {
            return self::check('cache', 'Cache', self::FAIL, 'Wrote a probe key and read back something else');
        }

        return self::check(
            'cache',
            'Cache',
            self::OK,
            (string) config('cache.default').' store read and wrote in one round trip',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function storage(): array
    {
        $path = storage_path('app');

        if (! is_dir($path) || ! is_writable($path)) {
            return self::check('storage', 'Storage', self::FAIL, "{$path} is not writable");
        }

        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total <= 0) {
            return self::check('storage', 'Storage', self::WARN, 'Writable, but free space cannot be read');
        }

        $percent = (int) round($free / $total * 100);
        $warn = (int) config('ops.thresholds.disk_free_warn_percent');
        $fail = (int) config('ops.thresholds.disk_free_fail_percent');

        $status = $percent <= $fail ? self::FAIL : ($percent <= $warn ? self::WARN : self::OK);

        return self::check(
            'storage',
            'Storage',
            $status,
            $percent.'% free ('.self::humanBytes((float) $free).' of '.self::humanBytes((float) $total).')',
            $percent,
            '% free',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function runtime(): array
    {
        $details = [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'environment' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'queue' => (string) config('queue.default'),
            'cache' => (string) config('cache.default'),
            'voice_driver' => (string) config('voice.driver'),
        ];

        $status = self::OK;
        $message = 'PHP '.PHP_VERSION.' · Laravel '.app()->version();

        // Debug mode in production leaks stack traces to clients.
        if (app()->environment('production') && config('app.debug') === true) {
            $status = self::FAIL;
            $message .= ' · debug mode is ON in production';
        } elseif (config('voice.driver') === 'log') {
            $status = self::WARN;
            $message .= ' · voice escalation is on the log driver';
        }

        if (Schema::hasTable('backups')) {
            $details['last_successful_backup'] = DB::table('backups')
                ->where('status', 'completed')
                ->max('finished_at');
        }

        return self::check('runtime', 'Runtime', $status, $message, null, null, $details);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private static function check(
        string $key,
        string $label,
        string $status,
        string $message,
        ?int $value = null,
        ?string $unit = null,
        array $details = [],
    ): array {
        return array_filter([
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'value' => $value,
            'unit' => $unit,
            'details' => $details === [] ? null : $details,
        ], static fn ($item) => $item !== null);
    }

    private static function humanBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 1).' '.$units[$index];
    }
}
