<?php

namespace App\Ledgers;

use App\Enums\LedgerEntryDirection;
use App\Enums\LedgerEntrySource;
use App\Models\LedgerEntry;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The bridge from a completed task to the household ledger.
 *
 * Completing a request that carries a ledger and an amount records exactly one
 * expense. It runs from {@see \App\Requests\RequestWorkflow::complete()} so it
 * happens whichever surface finished the task, and it is idempotent through the
 * unique index on `ledger_entries.request_id`: a retry (or a double tap) cannot
 * spend the money twice.
 *
 * The write is a system write — it goes through the audited RLS bypass — for one
 * reason worth saying out loud: the member who completes a task need not be a
 * member of the ledger it is charged to. The household put the task on the
 * board; the database should not refuse to record the money because the person
 * who ticked it off is not on the ledger's member list. The row is still pinned
 * to the ledger the request already carries, and `created_by`/`paid_by` name the
 * person who did it.
 */
class RequestExpenseRecorder
{
    public function record(ServiceRequest $request, User $actor): ?LedgerEntry
    {
        if ($request->ledger_id === null || $request->amount_minor === null) {
            return null;
        }

        return Tenancy::bypass(function () use ($request, $actor): ?LedgerEntry {
            if (LedgerEntry::query()->where('request_id', $request->getKey())->exists()) {
                return null;
            }

            try {
                // A savepoint, so a duplicate rejected by the unique index cannot
                // poison whatever transaction the completion is running inside.
                return DB::transaction(fn (): LedgerEntry => LedgerEntry::create([
                    'tenant_id' => $request->tenant_id,
                    'ledger_id' => $request->ledger_id,
                    'amount_minor' => (int) $request->amount_minor,
                    'direction' => LedgerEntryDirection::Expense,
                    'category' => $request->category,
                    'occurred_on' => Carbon::today()->toDateString(),
                    'paid_by' => $actor->getKey(),
                    'source' => LedgerEntrySource::Request,
                    'request_id' => $request->getKey(),
                    'created_by' => $actor->getKey(),
                ]));
            } catch (QueryException $exception) {
                // 23505 = unique_violation: another completion got there first.
                if ((string) $exception->getCode() === '23505') {
                    return null;
                }

                throw $exception;
            }
        });
    }
}
