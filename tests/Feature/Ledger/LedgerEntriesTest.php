<?php

namespace Tests\Feature\Ledger;

use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Entries and the aggregate view. Money is integer minor units everywhere.
 */
class LedgerEntriesTest extends TestCase
{
    use CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-18 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_an_owner_records_an_entry_and_reads_it_back(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $ledger = $this->createLedger($tenant, $admin, 'Groceries');
        $token = $this->tokenFor($admin);

        $this->withToken($token)
            ->postJson("/api/v1/ledgers/{$ledger->id}/entries", [
                'amount_minor' => 12_550,
                'direction' => 'expense',
                'category' => 'grocery',
                'description' => 'Weekly shop',
                'occurred_on' => '2026-10-17',
                'paid_by' => $admin->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount_minor', 12550)
            ->assertJsonPath('data.direction', 'expense')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.occurred_on', '2026-10-17')
            ->assertJsonPath('data.paid_by', $admin->id);

        $this->withToken($token)
            ->getJson("/api/v1/ledgers/{$ledger->id}/entries")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            // An integer in the JSON, never a float and never a formatted string.
            ->assertJsonPath('data.0.amount_minor', 12550)
            ->assertJsonPath('data.0.description', 'Weekly shop');
    }

    public function test_occurred_on_defaults_to_today(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $ledger = $this->createLedger($tenant, $admin);

        $this->withToken($this->tokenFor($admin))
            ->postJson("/api/v1/ledgers/{$ledger->id}/entries", ['amount_minor' => 500, 'direction' => 'expense'])
            ->assertCreated()
            ->assertJsonPath('data.occurred_on', '2026-10-18');
    }

    public function test_an_amount_must_be_a_positive_count_of_minor_units(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $ledger = $this->createLedger($tenant, $admin);
        $token = $this->tokenFor($admin);

        $this->withToken($token)
            ->postJson("/api/v1/ledgers/{$ledger->id}/entries", ['amount_minor' => 12.5, 'direction' => 'expense'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount_minor');

        $this->withToken($token)
            ->postJson("/api/v1/ledgers/{$ledger->id}/entries", ['amount_minor' => 0, 'direction' => 'expense'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount_minor');

        $this->withToken($token)
            ->postJson("/api/v1/ledgers/{$ledger->id}/entries", ['amount_minor' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors('direction');
    }

    public function test_the_person_who_paid_must_be_a_member_of_the_household(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $ledger = $this->createLedger($tenant, $admin);

        $other = $this->createTenant('Household B');
        $stranger = $this->createMember($other, 'member');

        $this->withToken($this->tokenFor($admin))
            ->postJson("/api/v1/ledgers/{$ledger->id}/entries", [
                'amount_minor' => 100, 'direction' => 'expense', 'paid_by' => $stranger->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('paid_by');
    }

    public function test_the_summary_reports_totals_and_categories_but_no_entries(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $watcher = $this->createMember($tenant, 'member');

        $ledger = $this->createLedger($tenant, $admin, 'Groceries');
        $this->addLedgerMember($ledger, $watcher, 'summary');

        $this->createLedgerEntry($ledger, $admin, [
            'amount_minor' => 3_000, 'category' => 'grocery', 'description' => 'Weekly shop',
            'occurred_on' => '2026-10-05',
        ]);
        $this->createLedgerEntry($ledger, $admin, [
            'amount_minor' => 1_000, 'category' => 'transport', 'description' => 'Bus fare',
            'occurred_on' => '2026-10-06',
        ]);
        $this->createLedgerEntry($ledger, $admin, [
            'amount_minor' => 5_000, 'direction' => 'income', 'description' => 'Refund',
            'occurred_on' => '2026-10-07',
        ]);

        $response = $this->withToken($this->tokenFor($watcher))
            ->getJson("/api/v1/ledgers/{$ledger->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.expense_minor', 4_000)
            ->assertJsonPath('data.income_minor', 5_000)
            ->assertJsonPath('data.net_minor', 1_000)
            ->assertJsonPath('data.entry_count', 3)
            ->assertJsonPath('data.by_category.0.label', 'grocery')
            ->assertJsonPath('data.by_category.0.expense_minor', 3_000)
            ->assertJsonPath('data.by_category.0.share', 0.75)
            ->assertJsonPath('data.by_category.1.label', 'transport')
            ->assertJsonPath('data.by_category.1.share', 0.25)
            // Default period: the current month.
            ->assertJsonPath('data.period.from', '2026-10-01')
            ->assertJsonPath('data.period.to', '2026-10-31');

        $body = (string) $response->getContent();

        // The aggregate view cannot leak an entry, however the client asks.
        $this->assertStringNotContainsString('Weekly shop', $body);
        $this->assertStringNotContainsString('Bus fare', $body);
        $this->assertStringNotContainsString('description', $body);
        $this->assertArrayNotHasKey('entries', $response->json('data'));
    }

    public function test_the_summary_honours_an_explicit_period(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $ledger = $this->createLedger($tenant, $admin);

        $this->createLedgerEntry($ledger, $admin, ['amount_minor' => 1_000, 'occurred_on' => '2026-09-30']);
        $this->createLedgerEntry($ledger, $admin, ['amount_minor' => 2_000, 'occurred_on' => '2026-10-04']);

        $this->withToken($this->tokenFor($admin))
            ->getJson("/api/v1/ledgers/{$ledger->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.expense_minor', 2_000)
            ->assertJsonPath('data.entry_count', 1);

        $this->withToken($this->tokenFor($admin))
            ->getJson("/api/v1/ledgers/{$ledger->id}/summary?from=2026-09-01&to=2026-09-30")
            ->assertOk()
            ->assertJsonPath('data.expense_minor', 1_000)
            ->assertJsonPath('data.entry_count', 1)
            ->assertJsonPath('data.period.from', '2026-09-01');
    }

    public function test_entries_can_be_deleted_but_only_from_their_own_ledger(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $groceries = $this->createLedger($tenant, $admin, 'Groceries');
        $household = $this->createLedger($tenant, $admin, 'Household');

        $entry = $this->createLedgerEntry($groceries, $admin, ['amount_minor' => 700]);

        $token = $this->tokenFor($admin);

        // Addressed through the wrong ledger: not found, and not deleted.
        $this->withToken($token)
            ->deleteJson("/api/v1/ledgers/{$household->id}/entries/{$entry->id}")
            ->assertNotFound();

        $this->withBypass(fn () => $this->assertDatabaseCount('ledger_entries', 1));

        $this->withToken($token)
            ->deleteJson("/api/v1/ledgers/{$groceries->id}/entries/{$entry->id}")
            ->assertOk();

        $this->withBypass(fn () => $this->assertDatabaseCount('ledger_entries', 0));

        $this->withBypass(fn () => $this->assertDatabaseHas('audit_events', ['action' => 'ledger.entry_deleted']));
    }

    public function test_a_summary_member_cannot_delete_an_entry(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $watcher = $this->createMember($tenant, 'member');

        $ledger = $this->createLedger($tenant, $admin);
        $this->addLedgerMember($ledger, $watcher, 'summary');

        $entry = $this->createLedgerEntry($ledger, $admin);

        $this->withToken($this->tokenFor($watcher))
            ->deleteJson("/api/v1/ledgers/{$ledger->id}/entries/{$entry->id}")
            ->assertForbidden();

        $this->withBypass(fn () => $this->assertDatabaseCount('ledger_entries', 1));
    }
}
