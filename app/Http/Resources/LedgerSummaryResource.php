<?php

namespace App\Http\Resources;

use App\Ledgers\LedgerSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The aggregate view and budget limiting analysis.
 *
 * This resource is a separate class from {@see LedgerEntryResource} on purpose:
 * a `summary` member may read this and may not read a single entry.
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
            'period_type' => $summary->periodType,
            'period' => [
                'from' => $summary->from->toDateString(),
                'to' => $summary->to->toDateString(),
            ],
            'expense_minor' => $summary->expenseMinor,
            'income_minor' => $summary->incomeMinor,
            'net_minor' => $summary->netMinor(),
            'entry_count' => $summary->entryCount,
            // Budget Limiting fields
            'budget_minor' => $summary->budgetMinor,
            'remaining_budget_minor' => $summary->remainingBudgetMinor,
            'budget_percent_used' => $summary->budgetPercentUsed,
            'is_over_budget' => $summary->isOverBudget,
            // Household Expense & Weekly Ledgers
            'by_category' => $summary->byCategory,
            'weekly_breakdown' => $summary->weeklyBreakdown,
        ];
    }
}
