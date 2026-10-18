<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Audit\AuditRecorder;
use App\Enums\LedgerEntryDirection;
use App\Enums\LedgerEntrySource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledgers\StoreLedgerEntryRequest;
use App\Http\Resources\LedgerEntryResource;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Entry-level access: owner and manager only. A `summary` member reaches these
 * routes and is refused by the policy (403) — they remain free to read the
 * aggregates.
 */
class LedgerEntryController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Ledger $ledger): JsonResponse
    {
        $this->authorize('viewEntries', $ledger);

        $entries = $ledger->entries()
            ->with('payer')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(50);

        return LedgerEntryResource::collection($entries)->response();
    }

    public function store(StoreLedgerEntryRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('createEntry', $ledger);

        $entry = LedgerEntry::create([
            'ledger_id' => $ledger->getKey(),
            'amount_minor' => $request->integer('amount_minor'),
            'direction' => LedgerEntryDirection::from($request->string('direction')->toString()),
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            // Money counts on the day it moved; "now" is the honest default.
            'occurred_on' => $request->filled('occurred_on')
                ? Carbon::parse($request->string('occurred_on')->toString())->toDateString()
                : Carbon::today()->toDateString(),
            'paid_by' => $request->input('paid_by'),
            'source' => LedgerEntrySource::Manual,
            'created_by' => $request->user()->getKey(),
        ]);

        $this->record($entry, $request->user()->getKey());

        return (new LedgerEntryResource($entry->load('payer')))->response()->setStatusCode(201);
    }

    public function destroy(Ledger $ledger, LedgerEntry $entry): JsonResponse
    {
        $this->authorize('deleteEntry', $ledger);

        // An entry id from another ledger is not addressable through this one.
        abort_if((int) $entry->ledger_id !== (int) $ledger->getKey(), 404);

        $this->audit->record('ledger.entry_deleted', [
            'ledger_id' => (int) $ledger->getKey(),
            'entry_id' => (int) $entry->getKey(),
            'amount_minor' => (int) $entry->amount_minor,
            'direction' => $entry->direction->value,
        ], 'warning');

        $entry->delete();

        return response()->json(['message' => 'Entry removed.']);
    }

    private function record(LedgerEntry $entry, int $actorId): void
    {
        $this->audit->record('ledger.entry_recorded', [
            'ledger_id' => (int) $entry->ledger_id,
            'entry_id' => (int) $entry->getKey(),
            'amount_minor' => (int) $entry->amount_minor,
            'direction' => $entry->direction->value,
            'actor_id' => $actorId,
        ]);
    }
}
