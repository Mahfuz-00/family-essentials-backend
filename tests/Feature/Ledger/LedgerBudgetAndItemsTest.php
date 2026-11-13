<?php

namespace Tests\Feature\Ledger;

use App\Models\Ledger;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class LedgerBudgetAndItemsTest extends TestCase
{
    use CreatesTenants;

    private Tenant $tenant;
    private User $owner;
    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-18 10:00:00');

        $this->tenant = $this->createTenant('The Millers');
        $this->owner = $this->createMember($this->tenant, 'tenant_admin');
        $this->ledger = $this->createLedger($this->tenant, $this->owner, 'Family Vault');

        // Set initial budget using bypass so RLS permits the setup update
        $this->withBypass(fn () => $this->ledger->update([
            'monthly_budget_minor' => 100_000, // $1,000.00
            'weekly_budget_minor' => 25_000,   // $250.00
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_can_set_and_update_budget_limits_on_ledger(): void
    {
        $token = $this->tokenFor($this->owner);

        $this->withToken($token)
            ->patchJson("/api/v1/ledgers/{$this->ledger->id}", [
                'name' => 'Updated Vault',
                'monthly_budget_minor' => 150_000,
                'weekly_budget_minor' => 35_000,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Vault')
            ->assertJsonPath('data.monthly_budget_minor', 150_000)
            ->assertJsonPath('data.weekly_budget_minor', 35_000);

        $this->withBypass(fn () => $this->assertDatabaseHas('ledgers', [
            'id' => $this->ledger->id,
            'name' => 'Updated Vault',
            'monthly_budget_minor' => 150_000,
            'weekly_budget_minor' => 35_000,
        ]));
    }

    public function test_summary_calculates_budget_limiting_and_weekly_breakdown(): void
    {
        $token = $this->tokenFor($this->owner);

        // Record an expense of $400 (40,000 minor)
        $this->withToken($token)
            ->postJson("/api/v1/ledgers/{$this->ledger->id}/entries", [
                'amount_minor' => 40_000,
                'direction' => 'expense',
                'category' => 'grocery',
                'description' => 'Weekly supermarket trip',
                'occurred_on' => '2026-10-10',
            ])
            ->assertCreated();

        // Monthly summary: budget is $1,000, spent $400, remaining $600
        $this->withToken($token)
            ->getJson("/api/v1/ledgers/{$this->ledger->id}/summary?period_type=monthly")
            ->assertOk()
            ->assertJsonPath('data.budget_minor', 100_000)
            ->assertJsonPath('data.expense_minor', 40_000)
            ->assertJsonPath('data.remaining_budget_minor', 60_000)
            ->assertJsonPath('data.budget_percent_used', 40)
            ->assertJsonPath('data.is_over_budget', false)
            ->assertJsonStructure(['data' => ['weekly_breakdown', 'by_category']]);

        // Record another expense of $700 (70,000 minor) -> total $1,100 ($100 over budget)
        $this->withToken($token)
            ->postJson("/api/v1/ledgers/{$this->ledger->id}/entries", [
                'amount_minor' => 70_000,
                'direction' => 'expense',
                'category' => 'rent',
                'description' => 'Apartment rent',
                'occurred_on' => '2026-10-12',
            ])
            ->assertCreated();

        $this->withToken($token)
            ->getJson("/api/v1/ledgers/{$this->ledger->id}/summary?period_type=monthly")
            ->assertOk()
            ->assertJsonPath('data.expense_minor', 110_000)
            ->assertJsonPath('data.remaining_budget_minor', -10_000)
            ->assertJsonPath('data.is_over_budget', true);
    }

    public function test_can_create_request_with_items_list_and_complete_with_quantities_and_expense(): void
    {
        $token = $this->tokenFor($this->owner);

        // Create request with multiple items and asked quantities
        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/requests', [
                'title' => 'Grocery Run',
                'category' => 'grocery',
                'items' => [
                    ['name' => 'Whole Milk', 'quantity_asked' => '2 gallons', 'is_picked' => false],
                    ['name' => 'Organic Eggs', 'quantity_asked' => '1 carton', 'is_picked' => false],
                    ['name' => 'Sourdough Bread', 'quantity_asked' => '2 loaves', 'is_picked' => false],
                ],
                'ledger_id' => $this->ledger->id,
                'amount_minor' => 3500, // estimated $35.00
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Grocery Run')
            ->assertJsonPath('data.items.0.name', 'Whole Milk')
            ->assertJsonPath('data.items.0.quantity_asked', '2 gallons');

        $requestId = $createResponse->json('data.id');

        // Member completes request, inputting actual quantities got (e.g. got 1 gallon of milk instead of 2) and actual cost
        $this->withToken($token)
            ->postJson("/api/v1/requests/{$requestId}/complete", [
                'quantity_got' => '3 items picked',
                'amount_minor' => 3250, // actual $32.50
                'ledger_id' => $this->ledger->id,
                'items' => [
                    ['name' => 'Whole Milk', 'quantity_asked' => '2 gallons', 'quantity_got' => '1 gallon', 'is_picked' => true],
                    ['name' => 'Organic Eggs', 'quantity_asked' => '1 carton', 'quantity_got' => '1 carton', 'is_picked' => true],
                    ['name' => 'Sourdough Bread', 'quantity_asked' => '2 loaves', 'quantity_got' => '2 loaves', 'is_picked' => true],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.quantity_got', '3 items picked')
            ->assertJsonPath('data.items.0.quantity_got', '1 gallon');

        // Check that ledger entry was recorded for $32.50
        $this->withBypass(fn () => $this->assertDatabaseHas('ledger_entries', [
            'tenant_id' => $this->tenant->id,
            'ledger_id' => $this->ledger->id,
            'request_id' => $requestId,
            'amount_minor' => 3250,
            'category' => 'grocery',
        ]));
    }
}
