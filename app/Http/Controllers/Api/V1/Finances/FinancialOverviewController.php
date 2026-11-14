<?php

namespace App\Http\Controllers\Api\V1\Finances;

use App\Http\Controllers\Controller;
use App\Models\FinancialAsset;
use App\Models\FinancialGoal;
use App\Models\FinancialLiability;
use App\Models\FinancialTax;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialOverviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = app(TenantContext::class)->tenant();
        $currency = (string) ($tenant?->settings['currency'] ?? config('ledger.currency', 'USD'));

        $assets = FinancialAsset::query()->get();
        $liabilities = FinancialLiability::query()->get();
        $goals = FinancialGoal::query()->get();
        $taxes = FinancialTax::query()->get();
        $ledgers = Ledger::query()->get();

        $monthlyBudget = (int) $ledgers->sum('monthly_budget_minor');
        $monthlySpend = (int) LedgerEntry::query()
            ->where('direction', 'debit')
            ->whereBetween('occurred_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount_minor');

        $totalAssets = (int) $assets->sum('balance_minor');
        $totalLiabilities = (int) $liabilities->where('status', '!=', 'paid_off')->sum('remaining_balance_minor');
        $netWorth = $totalAssets - $totalLiabilities;

        $savingsAssets = (int) $assets->whereIn('type', ['savings', 'cash'])->sum('balance_minor');
        $investmentAssets = (int) $assets->whereNotIn('type', ['savings', 'cash'])->sum('balance_minor');

        $activeLiabilities = $liabilities->where('status', '!=', 'paid_off');
        $mortgageLiabilities = (int) $activeLiabilities->where('type', 'mortgage')->sum('remaining_balance_minor');
        $otherLoans = (int) $activeLiabilities->where('type', '!=', 'mortgage')->sum('remaining_balance_minor');
        $monthlyDebtPayment = (int) $activeLiabilities->sum('monthly_payment_minor');

        $activeGoals = $goals->where('status', 'active');
        $goalsTarget = (int) $activeGoals->sum('target_amount_minor');
        $goalsSaved = (int) $activeGoals->sum('current_amount_minor');

        $pendingTaxes = (int) $taxes->whereIn('status', ['pending', 'overdue'])->sum('amount_minor');

        return response()->json([
            'data' => [
                'currency' => $currency,
                'net_worth_minor' => $netWorth,
                'total_assets_minor' => $totalAssets,
                'total_liabilities_minor' => $totalLiabilities,
                'savings_minor' => $savingsAssets,
                'investments_minor' => $investmentAssets,
                'mortgage_minor' => $mortgageLiabilities,
                'loans_minor' => $otherLoans,
                'monthly_debt_payment_minor' => $monthlyDebtPayment,
                'monthly_budget_minor' => $monthlyBudget,
                'monthly_spend_minor' => $monthlySpend,
                'goals' => [
                    'active_count' => $activeGoals->count(),
                    'target_minor' => $goalsTarget,
                    'saved_minor' => $goalsSaved,
                    'progress_percent' => $goalsTarget > 0 ? round(($goalsSaved / $goalsTarget) * 100, 1) : 0.0,
                ],
                'taxes' => [
                    'pending_minor' => $pendingTaxes,
                    'pending_count' => $taxes->whereIn('status', ['pending', 'overdue'])->count(),
                ],
                'counts' => [
                    'goals' => $goals->count(),
                    'liabilities' => $liabilities->count(),
                    'assets' => $assets->count(),
                    'taxes' => $taxes->count(),
                ],
            ],
        ]);
    }
}
