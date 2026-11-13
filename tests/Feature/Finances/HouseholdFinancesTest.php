<?php

namespace Tests\Feature\Finances;

use App\Models\FinancialAsset;
use App\Models\FinancialGoal;
use App\Models\FinancialLiability;
use App\Models\FinancialTax;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class HouseholdFinancesTest extends TestCase
{
    use CreatesTenants;

    public function test_registration_captures_currency_and_initializes_ledger(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice Morgan',
            'email' => 'alice.morgan@example.com',
            'phone' => '+15551239999',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'household_name' => 'Morgan Family',
            'currency' => 'EUR',
            'monthly_budget_minor' => 350000,
            'yearly_budget_minor' => 4200000,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'tenant', 'token']);

        $user = User::where('email', 'alice.morgan@example.com')->first();
        $this->assertNotNull($user);

        $this->withBypass(function () use ($user) {
            $tenant = Tenant::find($user->active_tenant_id);
            $this->assertNotNull($tenant);
            $this->assertSame('EUR', $tenant->settings['currency'] ?? null);

            $ledger = Ledger::where('tenant_id', $tenant->id)->first();
            $this->assertNotNull($ledger);
            $this->assertSame('Household Expenses', $ledger->name);
            $this->assertSame('EUR', $ledger->currency);
            $this->assertSame(350000, $ledger->monthly_budget_minor);
            $this->assertSame(4200000, $ledger->yearly_budget_minor);
        });
    }

    public function test_yearly_ledger_summary_reports_yearly_budget_and_monthly_breakdown(): void
    {
        $tenant = $this->createTenant('Finances Household');
        $owner = $this->createMember($tenant, 'tenant_admin', ['name' => 'Head of House']);
        $ledger = $this->createLedger($tenant, $owner, 'Family Ledger');

        $this->withBypass(function () use ($ledger, $owner) {
            $ledger->update([
                'yearly_budget_minor' => 1200000, // $12,000 cap
                'monthly_budget_minor' => 100000,
            ]);

            // Add entries across different months
            LedgerEntry::create([
                'tenant_id' => $ledger->tenant_id,
                'ledger_id' => $ledger->id,
                'direction' => 'expense',
                'amount_minor' => 40000, // $400 in Jan
                'category' => 'grocery',
                'description' => 'Groceries Jan',
                'occurred_on' => now()->startOfYear()->toDateString(),
                'source' => 'manual',
                'created_by' => $owner->id,
            ]);

            LedgerEntry::create([
                'tenant_id' => $ledger->tenant_id,
                'ledger_id' => $ledger->id,
                'direction' => 'expense',
                'amount_minor' => 900000, // $9,000 rent
                'category' => 'rent',
                'description' => 'Annual Rent',
                'occurred_on' => now()->startOfYear()->addMonths(2)->toDateString(),
                'source' => 'manual',
                'created_by' => $owner->id,
            ]);
        });

        $token = $this->tokenFor($owner);
        $response = $this->withToken($token)
            ->getJson("/api/v1/ledgers/{$ledger->id}/summary?period_type=yearly");

        $response->assertStatus(200)
            ->assertJsonPath('data.period_type', 'yearly')
            ->assertJsonPath('data.budget_minor', 1200000)
            ->assertJsonPath('data.expense_minor', 940000)
            ->assertJsonPath('data.remaining_budget_minor', 260000)
            ->assertJsonPath('data.is_over_budget', false);

        $breakdown = $response->json('data.weekly_breakdown');
        $this->assertIsArray($breakdown);
        $this->assertCount(12, $breakdown); // 12 monthly buckets for the full year
    }

    public function test_crud_and_overview_for_goals_liabilities_assets_and_taxes(): void
    {
        $tenant = $this->createTenant('Wealth Household');
        $member = $this->createMember($tenant, 'tenant_admin', ['name' => 'Family CFO']);

        $token = $this->tokenFor($member);

        // 1. Goal
        $goalRes = $this->withToken($token)
            ->postJson('/api/v1/finances/goals', [
                'name' => 'Emergency Fund',
                'category' => 'emergency',
                'target_amount_minor' => 1000000, // $10,000
                'current_amount_minor' => 350000, // $3,500
                'target_date' => now()->addMonths(6)->toDateString(),
            ]);
        $goalRes->assertStatus(201)
            ->assertJsonPath('data.progress_percent', 35);

        // 2. Liability (Mortgage)
        $liabilityRes = $this->withToken($token)
            ->postJson('/api/v1/finances/liabilities', [
                'name' => 'Primary Home Mortgage',
                'type' => 'mortgage',
                'lender' => 'Chase Home Lending',
                'principal_amount_minor' => 40000000, // $400,000
                'remaining_balance_minor' => 32000000, // $320,000
                'monthly_payment_minor' => 240000, // $2,400 / mo
                'interest_rate_bps' => 625, // 6.25%
                'due_day_of_month' => 1,
            ]);
        $liabilityRes->assertStatus(201)
            ->assertJsonPath('data.interest_rate_percent', 6.25)
            ->assertJsonPath('data.payoff_percent', 20);

        // 3. Asset (Investment & Savings)
        $assetRes = $this->withToken($token)
            ->postJson('/api/v1/finances/assets', [
                'name' => 'Vanguard S&P 500 ETF',
                'type' => 'investment',
                'institution' => 'Vanguard',
                'balance_minor' => 15000000, // $150,000
                'contribution_monthly_minor' => 100000,
                'return_rate_bps' => 850,
            ]);
        $assetRes->assertStatus(201)
            ->assertJsonPath('data.return_rate_percent', 8.5);

        // 4. Tax
        $taxRes = $this->withToken($token)
            ->postJson('/api/v1/finances/taxes', [
                'name' => '2026 County Property Tax',
                'type' => 'property',
                'tax_year' => 2026,
                'amount_minor' => 450000, // $4,500
                'due_date' => now()->addMonths(3)->toDateString(),
                'status' => 'pending',
            ]);
        $taxRes->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        // 5. Financial Overview
        $overviewRes = $this->withToken($token)
            ->getJson('/api/v1/finances/overview');

        $overviewRes->assertStatus(200)
            ->assertJsonPath('data.total_assets_minor', 15000000)
            ->assertJsonPath('data.total_liabilities_minor', 32000000)
            ->assertJsonPath('data.net_worth_minor', -17000000)
            ->assertJsonPath('data.mortgage_minor', 32000000)
            ->assertJsonPath('data.goals.active_count', 1)
            ->assertJsonPath('data.taxes.pending_minor', 450000);
    }
}
