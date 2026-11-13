<?php

namespace App\Ledgers;

use App\Models\Ledger;
use App\Models\LedgerEntry;
use Illuminate\Support\Carbon;

/**
 * The aggregate view of a ledger for a period: totals, a count, budget limits,
 * and breakdowns by category and weekly intervals.
 *
 * All the arithmetic happens on integers, so nothing can introduce a
 * floating-point rounding error into the household's money.
 */
final class LedgerSummary
{
    /**
     * Standard category labels for household expense and budget tracking.
     */
    public const CATEGORY_LABELS = [
        'rent' => 'Rent & Housing',
        'utility_bill' => 'Utility Bills',
        'utilities' => 'Utility Bills',
        'grocery' => 'Groceries & Food',
        'groceries' => 'Groceries & Food',
        'household' => 'Household & Supplies',
        'chores' => 'Errands & Tasks',
        'other' => 'Other Expenses',
    ];

    /**
     * @param  array<int, array{category: string, label: string, expense_minor: int, income_minor: int, share: float}>  $byCategory
     * @param  array<int, array{week_label: string, start_date: string, end_date: string, expense_minor: int, income_minor: int, net_minor: int}>  $weeklyBreakdown
     */
    public function __construct(
        public readonly int $ledgerId,
        public readonly string $currency,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly string $periodType,
        public readonly int $expenseMinor,
        public readonly int $incomeMinor,
        public readonly int $entryCount,
        public readonly ?int $budgetMinor,
        public readonly ?int $remainingBudgetMinor,
        public readonly ?float $budgetPercentUsed,
        public readonly bool $isOverBudget,
        public readonly array $byCategory,
        public readonly array $weeklyBreakdown,
    ) {}

    public static function forPeriod(
        Ledger $ledger,
        Carbon $from,
        Carbon $to,
        string $periodType = 'monthly',
    ): self {
        $period = [$from->toDateString(), $to->toDateString()];

        $totals = LedgerEntry::query()
            ->where('ledger_id', $ledger->getKey())
            ->whereBetween('occurred_on', $period)
            ->selectRaw("coalesce(sum(case when direction = 'expense' then amount_minor else 0 end), 0) as expense_minor")
            ->selectRaw("coalesce(sum(case when direction = 'income' then amount_minor else 0 end), 0) as income_minor")
            ->selectRaw('count(*) as entry_count')
            ->first();

        $expenseMinor = (int) ($totals->expense_minor ?? 0);
        $incomeMinor = (int) ($totals->income_minor ?? 0);

        // Calculate budget limiting based on period type
        $budgetMinor = null;
        if ($periodType === 'weekly') {
            $budgetMinor = $ledger->weekly_budget_minor
                ?: ($ledger->monthly_budget_minor ? (int) round($ledger->monthly_budget_minor / 4.33) : null);
        } elseif ($periodType === 'yearly') {
            $budgetMinor = $ledger->yearly_budget_minor
                ?: ($ledger->monthly_budget_minor ? (int) ($ledger->monthly_budget_minor * 12) : null);
        } else {
            $budgetMinor = $ledger->monthly_budget_minor
                ?: ($ledger->weekly_budget_minor ? (int) ($ledger->weekly_budget_minor * 4) : null);
        }

        $remainingBudgetMinor = $budgetMinor !== null ? ($budgetMinor - $expenseMinor) : null;
        $budgetPercentUsed = ($budgetMinor !== null && $budgetMinor > 0)
            ? round(($expenseMinor / $budgetMinor) * 100, 1)
            : null;
        $isOverBudget = ($budgetMinor !== null && $expenseMinor > $budgetMinor);

        // Breakdown by category
        $byCategoryRows = LedgerEntry::query()
            ->where('ledger_id', $ledger->getKey())
            ->whereBetween('occurred_on', $period)
            ->groupBy('category')
            ->selectRaw('category')
            ->selectRaw("coalesce(sum(case when direction = 'expense' then amount_minor else 0 end), 0) as category_expense")
            ->selectRaw("coalesce(sum(case when direction = 'income' then amount_minor else 0 end), 0) as category_income")
            ->orderByDesc('category_expense')
            ->get();

        $byCategory = $byCategoryRows->map(function (LedgerEntry $row) use ($expenseMinor): array {
            $catKey = filled($row->category) ? (string) $row->category : 'other';
            $label = filled($row->category) ? (string) $row->category : 'Uncategorized';
            $displayName = self::CATEGORY_LABELS[strtolower($catKey)] ?? (filled($row->category) ? ucwords(str_replace('_', ' ', $row->category)) : 'Other Expenses');
            $catExpense = (int) $row->getAttribute('category_expense');
            $catIncome = (int) $row->getAttribute('category_income');

            return [
                'category' => $catKey,
                'label' => $label,
                'display_name' => $displayName,
                'expense_minor' => $catExpense,
                'income_minor' => $catIncome,
                'share' => $expenseMinor > 0 ? round($catExpense / $expenseMinor, 4) : 0.0,
            ];
        })->all();

        // Sub-buckets breakdown (monthly breakdown for yearly periods, weekly for monthly/weekly)
        $weeklyBreakdown = $periodType === 'yearly'
            ? self::computeMonthlyBreakdown($ledger, $from, $to)
            : self::computeWeeklyBreakdown($ledger, $from, $to);

        return new self(
            ledgerId: (int) $ledger->getKey(),
            currency: (string) $ledger->currency,
            from: $from->copy(),
            to: $to->copy(),
            periodType: $periodType,
            expenseMinor: $expenseMinor,
            incomeMinor: $incomeMinor,
            entryCount: (int) ($totals->entry_count ?? 0),
            budgetMinor: $budgetMinor,
            remainingBudgetMinor: $remainingBudgetMinor,
            budgetPercentUsed: $budgetPercentUsed,
            isOverBudget: $isOverBudget,
            byCategory: $byCategory,
            weeklyBreakdown: $weeklyBreakdown,
        );
    }

    /**
     * Compute weekly sub-buckets within the period.
     *
     * @return array<int, array{week_label: string, start_date: string, end_date: string, expense_minor: int, income_minor: int, net_minor: int}>
     */
    private static function computeWeeklyBreakdown(Ledger $ledger, Carbon $from, Carbon $to): array
    {
        $breakdown = [];
        $cursor = $from->copy()->startOfWeek();
        $end = $to->copy()->endOfWeek();
        $weekIndex = 1;

        while ($cursor->lte($end)) {
            $weekStart = $cursor->copy()->startOfDay();
            $weekEnd = $cursor->copy()->endOfWeek()->startOfDay();

            if ($weekEnd->gt($to)) {
                $weekEnd = $to->copy()->startOfDay();
            }
            if ($weekStart->lt($from)) {
                $weekStart = $from->copy()->startOfDay();
            }

            $wTotals = LedgerEntry::query()
                ->where('ledger_id', $ledger->getKey())
                ->whereBetween('occurred_on', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->selectRaw("coalesce(sum(case when direction = 'expense' then amount_minor else 0 end), 0) as expense_minor")
                ->selectRaw("coalesce(sum(case when direction = 'income' then amount_minor else 0 end), 0) as income_minor")
                ->first();

            $wExpense = (int) ($wTotals->expense_minor ?? 0);
            $wIncome = (int) ($wTotals->income_minor ?? 0);

            $breakdown[] = [
                'week_label' => sprintf('Week %d (%s - %s)', $weekIndex, $weekStart->format('M j'), $weekEnd->format('M j')),
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'expense_minor' => $wExpense,
                'income_minor' => $wIncome,
                'net_minor' => $wIncome - $wExpense,
            ];

            $cursor->addWeek();
            $weekIndex++;
        }

        return $breakdown;
    }

    /**
     * Compute monthly sub-buckets within the yearly period (12 months).
     *
     * @return array<int, array{week_label: string, start_date: string, end_date: string, expense_minor: int, income_minor: int, net_minor: int}>
     */
    private static function computeMonthlyBreakdown(Ledger $ledger, Carbon $from, Carbon $to): array
    {
        $breakdown = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            $monthStart = $cursor->copy()->startOfDay();
            $monthEnd = $cursor->copy()->endOfMonth()->endOfDay();

            $mTotals = LedgerEntry::query()
                ->where('ledger_id', $ledger->getKey())
                ->whereBetween('occurred_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->selectRaw("coalesce(sum(case when direction = 'expense' then amount_minor else 0 end), 0) as expense_minor")
                ->selectRaw("coalesce(sum(case when direction = 'income' then amount_minor else 0 end), 0) as income_minor")
                ->first();

            $mExpense = (int) ($mTotals->expense_minor ?? 0);
            $mIncome = (int) ($mTotals->income_minor ?? 0);

            $breakdown[] = [
                'week_label' => $monthStart->format('M Y'),
                'start_date' => $monthStart->toDateString(),
                'end_date' => $monthEnd->toDateString(),
                'expense_minor' => $mExpense,
                'income_minor' => $mIncome,
                'net_minor' => $mIncome - $mExpense,
            ];

            $cursor->addMonth();
        }

        return $breakdown;
    }

    public function netMinor(): int
    {
        return $this->incomeMinor - $this->expenseMinor;
    }
}
