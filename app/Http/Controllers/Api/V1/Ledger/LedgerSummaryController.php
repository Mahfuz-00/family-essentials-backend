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

        $periodType = $request->string('period_type', 'monthly')->toString();

        if ($periodType === 'weekly') {
            $from = $request->filled('from')
                ? Carbon::parse($request->string('from')->toString())->startOfDay()
                : Carbon::today()->startOfWeek();

            $to = $request->filled('to')
                ? Carbon::parse($request->string('to')->toString())->endOfDay()
                : Carbon::today()->endOfWeek()->endOfDay();
        } elseif ($periodType === 'yearly') {
            $from = $request->filled('from')
                ? Carbon::parse($request->string('from')->toString())->startOfDay()
                : Carbon::today()->startOfYear();

            $to = $request->filled('to')
                ? Carbon::parse($request->string('to')->toString())->endOfDay()
                : Carbon::today()->endOfYear()->endOfDay();
        } else {
            $from = $request->filled('from')
                ? Carbon::parse($request->string('from')->toString())->startOfDay()
                : Carbon::today()->startOfMonth();

            $to = $request->filled('to')
                ? Carbon::parse($request->string('to')->toString())->endOfDay()
                : Carbon::today()->endOfMonth()->endOfDay();
        }

        $summary = LedgerSummary::forPeriod($ledger, $from, $to, $periodType);

        return (new LedgerSummaryResource($summary))->response();
    }
}
