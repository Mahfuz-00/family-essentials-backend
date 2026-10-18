<?php

namespace App\Ledgers;

use App\Models\Ledger;
use App\Models\LedgerEntry;
use Illuminate\Support\Carbon;

/**
 * The aggregate view of a ledger for a period: totals, a count, and a breakdown
 * by category. It deliberately has no way to name an entry — the `summary` role
 * receives this object and nothing richer.
 *
 * All the arithmetic happens in the database on integers, so nothing here can
 * introduce a floating-point rounding error into the household's money.
 */
final class LedgerSummary
{
    /**
     * @param  array<int, array{label: string, expense_minor: int, share: float}>  $byCategory
     */
    public function __construct(
        public readonly int $ledgerId,
        public readonly string $currency,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly int $expenseMinor,
        public readonly int $incomeMinor,
        public readonly int $entryCount,
        public readonly array $byCategory,
    ) {}

    public static function forPeriod(Ledger $ledger, Carbon $from, Carbon $to): self
    {
        $period = [$from->toDateString(), $to->toDateString()];

        $totals = LedgerEntry::query()
            ->where('ledger_id', $ledger->getKey())
            ->whereBetween('occurred_on', $period)
            ->selectRaw("coalesce(sum(case when direction = 'expense' then amount_minor else 0 end), 0) as expense_minor")
            ->selectRaw("coalesce(sum(case when direction = 'income' then amount_minor else 0 end), 0) as income_minor")
            ->selectRaw('count(*) as entry_count')
            ->first();

        $expenseMinor = (int) ($totals->expense_minor ?? 0);

        $byCategory = LedgerEntry::query()
            ->where('ledger_id', $ledger->getKey())
            ->whereBetween('occurred_on', $period)
            ->where('direction', 'expense')
            ->groupBy('category')
            ->selectRaw('category, sum(amount_minor) as category_total')
            ->orderByDesc('category_total')
            ->get()
            ->map(fn (LedgerEntry $row): array => [
                'label' => filled($row->category) ? (string) $row->category : 'Uncategorized',
                'expense_minor' => (int) $row->category_total,
                // A ratio, not money: the minor-unit rule applies to amounts.
                'share' => $expenseMinor > 0 ? round((int) $row->category_total / $expenseMinor, 4) : 0.0,
            ])
            ->all();

        return new self(
            ledgerId: (int) $ledger->getKey(),
            currency: (string) $ledger->currency,
            from: $from->copy(),
            to: $to->copy(),
            expenseMinor: $expenseMinor,
            incomeMinor: (int) ($totals->income_minor ?? 0),
            entryCount: (int) ($totals->entry_count ?? 0),
            byCategory: $byCategory,
        );
    }

    public function netMinor(): int
    {
        return $this->incomeMinor - $this->expenseMinor;
    }
}
