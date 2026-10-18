<?php

namespace App\Console\Commands;

use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reaps the tables that only ever grow.
 *
 * The policy lives in config/retention.php; this command is only the mechanism.
 * Three properties matter here:
 *
 *  - **Batched.** A first run against a table with years of history deletes in
 *    bounded chunks rather than in one statement that holds locks for minutes
 *    and bloats the transaction. `retention.batch_size` is the bound.
 *  - **Idempotent and safe to run concurrently.** Each batch re-reads the keys
 *    it is about to delete, so rows another run already removed are simply not
 *    there; a batch that deletes nothing ends that table's loop rather than
 *    spinning. Nothing is assumed about the other run's position.
 *  - **Under the audited RLS bypass.** Every tenant table here is `FORCE ROW
 *    LEVEL SECURITY`, so without the bypass the policies would make all of them
 *    look empty and the command would report a clean sweep while retaining
 *    everything. This is the same bypass the other cross-tenant maintenance
 *    commands use.
 *
 * Every table it touches must have an `id` primary key to batch by.
 */
class PruneHistoryCommand extends Command
{
    protected $signature = 'essentials:prune-history
                            {--dry-run : Report what would be deleted without deleting anything}';

    protected $description = 'Delete history past each table\'s retention window (config/retention.php)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->line('Dry run: reporting only, nothing will be deleted.');
        }

        Tenancy::bypass(function () use ($dryRun): void {
            foreach ((array) config('retention.tables') as $table => $window) {
                $this->pruneTable((string) $table, (array) $window, $dryRun);
            }

            $this->pruneExpiredTokens($dryRun);
            $this->pruneConsumedCodes($dryRun);
        });

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $window
     */
    private function pruneTable(string $table, array $window, bool $dryRun): void
    {
        $column = (string) ($window['column'] ?? 'created_at');
        $days = $window['days'] ?? null;

        // No window configured — null, or an env var left empty — means "keep
        // forever". `audit_events` and `feedback_reports` depend on this.
        if ($days === null || $days === '') {
            return;
        }

        $days = (int) $days;
        $cutoff = Carbon::now()->subDays($days);

        $deleted = $this->deleteBatched(
            $table,
            fn (Builder $query): Builder => $query->where($column, '<', $cutoff),
            $dryRun,
        );

        $this->report($table, $deleted, sprintf('%d-day window, before %s', $days, $cutoff->toDateString()), $dryRun);
    }

    private function pruneExpiredTokens(bool $dryRun): void
    {
        // Sanctum tokens are not windowed by age: an expired token is litter
        // however recently it was minted, and a token with no `expires_at` is a
        // deliberate choice that must survive every run.
        $deleted = $this->deleteBatched(
            'personal_access_tokens',
            fn (Builder $query): Builder => $query
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', Carbon::now()),
            $dryRun,
        );

        $this->report('personal_access_tokens', $deleted, 'expired tokens', $dryRun);
    }

    private function pruneConsumedCodes(bool $dryRun): void
    {
        // A code is spent the moment it verifies — that row is deleted as it is
        // used — so what is left is litter: codes whose TTL ran out, and codes
        // whose attempt budget was exhausted without ever matching.
        $maxAttempts = (int) config('sms.max_attempts');

        $deleted = $this->deleteBatched(
            'phone_verification_codes',
            fn (Builder $query): Builder => $query
                ->where('expires_at', '<', Carbon::now())
                ->orWhere('attempts', '>=', $maxAttempts),
            $dryRun,
        );

        $this->report('phone_verification_codes', $deleted, 'expired or used-up codes', $dryRun);
    }

    /**
     * Delete in bounded batches, oldest key first, and return how many rows went.
     *
     * @param  Closure(Builder): Builder  $scope
     */
    private function deleteBatched(string $table, Closure $scope, bool $dryRun): int
    {
        if ($dryRun) {
            return (int) $scope(DB::table($table))->count();
        }

        $batch = max(1, (int) config('retention.batch_size', 1000));
        $deleted = 0;

        while (true) {
            $keys = $scope(DB::table($table))->orderBy('id')->limit($batch)->pluck('id');

            if ($keys->isEmpty()) {
                break;
            }

            // Delete by the keys just read, so a concurrent run can only ever
            // remove rows this one was about to remove anyway.
            $affected = DB::table($table)->whereIn('id', $keys->all())->delete();
            $deleted += $affected;

            if ($affected === 0 || $keys->count() < $batch) {
                break;
            }
        }

        return $deleted;
    }

    private function report(string $table, int $rows, string $window, bool $dryRun): void
    {
        $this->line(sprintf(
            '%-26s %6d %s (%s)',
            $table,
            $rows,
            $dryRun ? 'would be deleted' : 'deleted',
            $window,
        ));
    }
}
