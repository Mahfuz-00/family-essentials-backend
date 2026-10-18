<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledgers\LedgerSummaryRequest;
use App\Http\Resources\LedgerSummaryResource;
use App\Ledgers\LedgerSummary;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Totals for a period, open to every member of the ledger — including the ones
 * who may not see a single entry.
 */
class LedgerSummaryController extends Controller
{
    public function show(LedgerSummaryRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('viewSummary', $ledger);

        // Default period: the current month.
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : Carbon::today()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->startOfDay()
            : Carbon::today()->endOfMonth()->startOfDay();

        $summary = LedgerSummary::forPeriod($ledger, $from, $to);

        return (new LedgerSummaryResource($summary))->response();
    }
}
