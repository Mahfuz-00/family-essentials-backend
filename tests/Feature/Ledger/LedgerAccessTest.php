<?php

namespace Tests\Feature\Ledger;

use App\Models\Ledger;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Who may see a ledger, and how much of it.
 */
class LedgerAccessTest extends TestCase
{
    use CreatesTenants;

    public function test_creating_a_ledger_makes_the_caller_its_owner(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $token = $this->tokenFor($admin);

        $this->withToken($token)
            ->postJson('/api/v1/ledgers', ['name' => 'Groceries'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Groceries')
            ->assertJsonPath('data.currency', config('ledger.currency'))
            ->assertJsonPath('data.my_role', 'owner')
            ->assertJsonPath('data.member_count', 1);

        // The membership row is written in the same transaction as the ledger:
        // a ledger nobody is a member of would be invisible to everyone.
        $this->withBypass(fn () => $this->assertDatabaseHas('ledger_members', [
            'user_id' => $admin->id,
            'role' => 'owner',
        ]));

        $this->withToken($token)
            ->getJson('/api/v1/ledgers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.my_role', 'owner')
            ->assertJsonPath('data.0.member_count', 1);
    }

    public function test_the_owner_shares_the_ledger_and_the_roles_decide_the_depth(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $manager = $this->createMember($tenant, 'member');
        $watcher = $this->createMember($tenant, 'member', ['name' => 'Watcher']);

        $ledger = $this->createLedger($tenant, $admin, 'Groceries');
        $token = $this->tokenFor($admin);

        $this->withToken($token)
            ->postJson("/api/v1/ledgers/{$ledger->id}/members", ['user_id' => $manager->id, 'role' => 'manager'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.name', $manager->name);

        $this->withToken($token)
            ->postJson("/api/v1/ledgers/{$ledger->id}/members", ['user_id' => $watcher->id, 'role' => 'summary'])
            ->assertCreated();

        $this->withToken($token)
            ->getJson('/api/v1/ledgers')
            ->assertOk()
            ->assertJsonPath('data.0.member_count', 3);

        // A manager reads and writes entries.
        $this->withToken($this->tokenFor($manager))
            ->postJson("/api/v1/ledgers/{$ledger->id}/entries", [
                'amount_minor' => 1_250,
                'direction' => 'expense',
                'category' => 'grocery',
            ])
            ->assertCreated();

        $this->withToken($this->tokenFor($manager))
            ->getJson("/api/v1/ledgers/{$ledger->id}/entries")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // A summary member reaches the ledger and sees the totals only.
        $watcherToken = $this->tokenFor($watcher);

        $this->withToken($watcherToken)
            ->getJson("/api/v1/ledgers/{$ledger->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.expense_minor', 1250)
            ->assertJsonPath('data.entry_count', 1);

        $this->withToken($watcherToken)
            ->getJson("/api/v1/ledgers/{$ledger->id}/entries")
            ->assertForbidden();

        $this->withToken($watcherToken)
            ->postJson("/api/v1/ledgers/{$ledger->id}/entries", [
                'amount_minor' => 100,
                'direction' => 'expense',
            ])
            ->assertForbidden();

        // A manager cannot change membership: that belongs to the owner.
        $this->withToken($this->tokenFor($manager))
            ->postJson("/api/v1/ledgers/{$ledger->id}/members", ['user_id' => $watcher->id, 'role' => 'manager'])
            ->assertForbidden();
    }

    public function test_a_member_of_the_household_who_is_not_in_the_ledger_gets_a_404(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $outsider = $this->createMember($tenant, 'member');

        $ledger = $this->createLedger($tenant, $admin, 'Groceries');
        $token = $this->tokenFor($outsider);

        // Not 403: nothing may reveal that this ledger exists at all.
        $this->withToken($token)->getJson("/api/v1/ledgers/{$ledger->id}/entries")->assertNotFound();
        $this->withToken($token)->getJson("/api/v1/ledgers/{$ledger->id}/summary")->assertNotFound();
        $this->withToken($token)->postJson("/api/v1/ledgers/{$ledger->id}/entries", [
            'amount_minor' => 100, 'direction' => 'expense',
        ])->assertNotFound();
        $this->withToken($token)->postJson("/api/v1/ledgers/{$ledger->id}/members", [
            'user_id' => $outsider->id, 'role' => 'owner',
        ])->assertNotFound();
        $this->withToken($token)->deleteJson("/api/v1/ledgers/{$ledger->id}/members/{$admin->id}")->assertNotFound();

        $this->withToken($token)->getJson('/api/v1/ledgers')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_ledger_from_another_household_is_a_404(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');

        $tenantB = $this->createTenant('Household B');
        $adminB = $this->createMember($tenantB, 'tenant_admin');
        $ledgerB = $this->createLedger($tenantB, $adminB, 'Their groceries');

        $tokenA = $this->tokenFor($adminA);

        $this->withToken($tokenA)->getJson('/api/v1/ledgers')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($tokenA)->getJson("/api/v1/ledgers/{$ledgerB->id}/summary")->assertNotFound();
        $this->withToken($tokenA)->getJson("/api/v1/ledgers/{$ledgerB->id}/entries")->assertNotFound();
        $this->withToken($tokenA)->postJson("/api/v1/ledgers/{$ledgerB->id}/members", [
            'user_id' => $adminA->id, 'role' => 'owner',
        ])->assertNotFound();
    }

    public function test_membership_can_only_be_granted_to_a_member_of_the_same_household(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');
        $ledger = $this->createLedger($tenantA, $adminA);

        $tenantB = $this->createTenant('Household B');
        $stranger = $this->createMember($tenantB, 'member');

        $this->withToken($this->tokenFor($adminA))
            ->postJson("/api/v1/ledgers/{$ledger->id}/members", ['user_id' => $stranger->id, 'role' => 'manager'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_removing_a_member_revokes_access_on_the_very_next_request(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $manager = $this->createMember($tenant, 'member');

        $ledger = $this->createLedger($tenant, $admin, 'Groceries');
        $this->withToken($this->tokenFor($admin))
            ->postJson("/api/v1/ledgers/{$ledger->id}/members", ['user_id' => $manager->id, 'role' => 'manager'])
            ->assertCreated();

        $managerToken = $this->tokenFor($manager);

        $this->withToken($managerToken)->getJson('/api/v1/ledgers')->assertJsonCount(1, 'data');
        $this->withToken($managerToken)->getJson("/api/v1/ledgers/{$ledger->id}/entries")->assertOk();

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/ledgers/{$ledger->id}/members/{$manager->id}")
            ->assertOk();

        // Same token, next request: gone.
        $this->withToken($managerToken)->getJson('/api/v1/ledgers')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($managerToken)->getJson("/api/v1/ledgers/{$ledger->id}/entries")->assertNotFound();
        $this->withToken($managerToken)->getJson("/api/v1/ledgers/{$ledger->id}/summary")->assertNotFound();
    }

    public function test_the_owner_cannot_remove_themselves(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $ledger = $this->createLedger($tenant, $admin, 'Groceries');

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/ledgers/{$ledger->id}/members/{$admin->id}")
            ->assertForbidden();

        $this->withBypass(fn () => $this->assertDatabaseHas('ledger_members', [
            'ledger_id' => $ledger->id, 'user_id' => $admin->id,
        ]));
    }

    public function test_removing_somebody_who_is_not_a_member_is_a_404(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $bystander = $this->createMember($tenant, 'member');
        $ledger = $this->createLedger($tenant, $admin, 'Groceries');

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/v1/ledgers/{$ledger->id}/members/{$bystander->id}")
            ->assertNotFound();
    }

    public function test_creating_a_ledger_and_changing_membership_are_audited(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($admin);

        $ledgerId = $this->withToken($token)
            ->postJson('/api/v1/ledgers', ['name' => 'Groceries'])
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/v1/ledgers/{$ledgerId}/members", ['user_id' => $member->id, 'role' => 'manager'])
            ->assertCreated();

        $this->withToken($token)
            ->deleteJson("/api/v1/ledgers/{$ledgerId}/members/{$member->id}")
            ->assertOk();

        $this->withBypass(function (): void {
            $this->assertSame(1, \App\Models\AuditEvent::query()->where('action', 'ledger.created')->count());
            $this->assertSame(1, \App\Models\AuditEvent::query()->where('action', 'ledger.member_added')->count());
            $this->assertSame(1, \App\Models\AuditEvent::query()->where('action', 'ledger.member_removed')->count());
        });
    }

    public function test_the_mobile_surface_serves_the_same_ledgers(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $token = $this->postJson('/mobile/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
            'device_name' => 'flutter',
        ])->assertOk()->json('token');

        $id = $this->withToken($token)
            ->postJson('/mobile/v1/ledgers', ['name' => 'Groceries'])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/mobile/v1/ledgers/{$id}/entries", ['amount_minor' => 900, 'direction' => 'expense'])
            ->assertCreated();

        // The dashboard sees the ledger the app created.
        $this->withToken($this->tokenFor($admin))
            ->getJson("/api/v1/ledgers/{$id}/entries")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount_minor', 900);

        $this->withBypass(fn () => $this->assertSame(1, Ledger::query()->count()));
    }
}
