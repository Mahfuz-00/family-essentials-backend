<?php

namespace App\Http\Resources;

use App\Ledgers\LedgerSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The aggregate view, and nothing else.
 *
 * This resource is a separate class from {@see LedgerEntryResource} on purpose:
 * a `summary` member may read this and may not read a single entry, and the
 * cheapest way to guarantee that is to render from an object that has no entry
 * rows and no descriptions in it at all.
 */
class LedgerSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LedgerSummary $summary */
        $summary = $this->resource;

        return [
            'ledger_id' => $summary->ledgerId,
            'currency' => $summary->currency,
            'period' => [
                'from' => $summary->from->toDateString(),
                'to' => $summary->to->toDateString(),
            ],
            'expense_minor' => $summary->expenseMinor,
            'income_minor' => $summary->incomeMinor,
            'net_minor' => $summary->netMinor(),
            'entry_count' => $summary->entryCount,
            'by_category' => $summary->byCategory,
        ];
    }
}
