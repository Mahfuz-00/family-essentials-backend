<?php

namespace App\Console\Commands;

use App\Models\RecurringRule;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Requests\RequestWorkflow;
use App\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Turns recurring rules into work.
 *
 * A rule spawns a *fresh* request each cycle and moves its own clock forward. It
 * never reopens an old request: that would rewrite the board's history and turn
 * one chore into two entries in the audit ledger.
 *
 * The command is idempotent. Running it twice on the same day spawns nothing the
 * second time, because a cycle is identified by (recurring_rule_id,
 * recurring_for) and PostgreSQL holds that pair unique. A missed cycle is
 * skipped, not back-filled: `next_run_on` always advances to the next occurrence
 * strictly after today.
 */
class SpawnRecurringRequests extends Command
{
    protected $signature = 'essentials:spawn-recurring';

    protected $description = 'Spawn a fresh request for every recurring rule whose next run has arrived.';

    public function handle(RequestWorkflow $workflow): int
    {
        $today = Carbon::today();

        // Cross-tenant scan under the audited bypass: the work that follows is
        // scoped to one household at a time, as the member who wrote the rule.
        $candidates = Tenancy::bypass(fn (): array => RecurringRule::query()
            ->where('active', true)
            ->whereDate('next_run_on', '<=', $today->toDateString())
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'created_by'])
            ->all());

        $spawned = 0;
        $duplicate = 0;
        $failed = 0;

        foreach ($candidates as $candidate) {
            try {
                $outcome = Tenancy::forTenant(
                    (int) $candidate->tenant_id,
                    fn (): array => $this->process((int) $candidate->id, $today, $workflow),
                    (int) $candidate->created_by,
                );

                if ($outcome['spawned']) {
                    $spawned++;
                } else {
                    $duplicate++;
                }

                $this->line($outcome['summary']);
            } catch (Throwable $exception) {
                $failed++;
                $this->error(sprintf('Rule #%d failed: %s', $candidate->id, $exception->getMessage()));
                report($exception);
            }
        }

        $this->info(sprintf(
            'Recurring: %d spawned, %d already handled, %d failed (%d rule(s) due).',
            $spawned,
            $duplicate,
            $failed,
            $candidates === [] ? 0 : count($candidates),
        ));

        // Non-zero only when something actually went wrong.
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * One rule, inside its own household context.
     *
     * @return array{spawned: bool, summary: string}
     */
    private function process(int $id, Carbon $today, RequestWorkflow $workflow): array
    {
        $rule = RecurringRule::query()->find($id);

        if ($rule === null || ! $rule->isDue($today)) {
            return [
                'spawned' => false,
                'summary' => sprintf('Rule #%d: no longer due, left alone.', $id),
            ];
        }

        // The cycle being spawned, captured before the clock moves.
        $cycle = $rule->next_run_on->copy();
        $label = sprintf('Rule #%d ("%s")', $id, $rule->title);

        $creator = User::query()->find($rule->created_by);

        if ($creator === null) {
            throw new RuntimeException('the member who created this rule no longer exists');
        }

        $request = $this->spawn($rule, $creator, $cycle, $workflow);

        if ($request === null) {
            $duplicate = true;
            $summary = sprintf('%s: cycle %s was already spawned, nothing created.', $label, $cycle->toDateString());
        } else {
            $duplicate = false;
            $summary = sprintf(
                '%s: spawned request #%d for %s.',
                $label,
                $request->getKey(),
                $cycle->toDateString(),
            );
        }

        // Advance strictly past today, so a missed cycle is skipped rather than
        // replayed, and record that the rule ran.
        $rule->scheduleNextRun($today);
        $rule->forceFill(['last_run_on' => $today->toDateString()])->save();

        return [
            'spawned' => ! $duplicate,
            'summary' => $summary.sprintf(' Next run %s.', $rule->next_run_on->toDateString()),
        ];
    }

    /**
     * Create the cycle's request, or nothing when it exists already.
     */
    private function spawn(
        RecurringRule $rule,
        User $creator,
        Carbon $cycle,
        RequestWorkflow $workflow,
    ): ?ServiceRequest {
        $attributes = [
            'title' => $rule->title,
            'quantity' => $rule->quantity,
            'notes' => $rule->notes,
            'category' => $rule->category,
            'priority' => $rule->priority,
            'assignee_id' => $rule->assignee_id,
            'place_id' => $rule->place_id,
            'location_trigger_place_id' => $rule->location_trigger_place_id,
            'location_trigger_direction' => $rule->location_trigger_direction,
            'amount_minor' => $rule->amount_minor,
            'ledger_id' => $rule->ledger_id,
            'recurring_rule_id' => $rule->getKey(),
            'recurring_for' => $cycle->toDateString(),
        ];

        $exists = ServiceRequest::query()
            ->where('recurring_rule_id', $rule->getKey())
            ->whereDate('recurring_for', $cycle->toDateString())
            ->exists();

        if ($exists) {
            return null;
        }

        try {
            // A savepoint, so a duplicate rejected by the unique index cannot
            // poison the surrounding transaction.
            return DB::transaction(fn (): ServiceRequest => $workflow->create($creator, $attributes));
        } catch (QueryException $exception) {
            // 23505 = unique_violation: a concurrent run got there first.
            if ((string) $exception->getCode() === '23505') {
                return null;
            }

            throw $exception;
        }
    }
}
