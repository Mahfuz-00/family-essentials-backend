<?php

namespace Tests\Feature\Ledger;

use App\Models\LedgerEntry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Money follows the task, not the surface: completing a request that carries a
 * ledger records exactly one expense, and a request with no ledger records none.
 */
class RequestExpenseBridgeTest extends TestCase
{
    use CreatesTenants;

    public function test_completing_a_task_records_one_expense(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $ledger = $this->createLedger($tenant, $member);

        $request = $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Weekly shop',
            'assignee_id' => $member->id,
            'category' => 'grocery',
            'ledger_id' => $ledger->id,
            'amount_minor' => 4500,
        ]));

        $this->withToken($this->tokenFor($member))
            ->postJson("/api/v1/requests/{$request->id}/complete")
            ->assertOk();

        $this->withToken($this->tokenFor($member))
            ->getJson("/api/v1/ledgers/{$ledger->id}/entries")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount_minor', 4500)
            ->assertJsonPath('data.0.direction', 'expense')
            ->assertJsonPath('data.0.source', 'request')
            ->assertJsonPath('data.0.category', 'grocery');
    }

    public function test_a_task_without_a_ledger_records_nothing(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $ledger = $this->createLedger($tenant, $member);

        $request = $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Bins out',
            'assignee_id' => $member->id,
            'amount_minor' => 4500,
        ]));

        $this->withToken($this->tokenFor($member))
            ->postJson("/api/v1/requests/{$request->id}/complete")
            ->assertOk();

        $this->withToken($this->tokenFor($member))
            ->getJson("/api/v1/ledgers/{$ledger->id}/entries")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withBypass(fn () => $this->assertSame(
            0,
            LedgerEntry::withoutGlobalScope('tenant')->count(),
        ));
    }

    public function test_a_second_expense_for_one_task_is_impossible(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $ledger = $this->createLedger($tenant, $member);

        $request = $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Weekly shop',
            'assignee_id' => $member->id,
            'ledger_id' => $ledger->id,
            'amount_minor' => 4500,
        ]));

        $this->withToken($this->tokenFor($member))
            ->postJson("/api/v1/requests/{$request->id}/complete")
            ->assertOk();

        // The unique index on request_id is the guard, not the caller's
        // discipline: a retried completion cannot double the money.
        //
        // The attempt runs in its own savepoint, because a unique violation
        // aborts the surrounding transaction and the rest of the test with it.
        $raised = false;

        try {
            $this->withBypass(fn () => DB::transaction(fn () => LedgerEntry::withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenant->id,
                'ledger_id' => $ledger->id,
                'amount_minor' => 4500,
                'direction' => 'expense',
                'occurred_on' => now()->toDateString(),
                'source' => 'request',
                'request_id' => $request->id,
                'created_by' => $member->id,
            ])));
        } catch (QueryException) {
            $raised = true;
        }

        $this->assertTrue($raised, 'A second expense for one task must be rejected.');
    }
}
