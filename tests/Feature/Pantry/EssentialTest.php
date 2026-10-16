<?php

namespace Tests\Feature\Pantry;

use App\Models\Essential;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class EssentialTest extends TestCase
{
    use CreatesTenants;

    public function test_a_household_member_can_track_and_restock_a_staple(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $id = $this->withToken($token)
            ->postJson('/api/v1/essentials', [
                'name' => 'Organic Whole Milk',
                'category' => 'dairy',
                'default_store' => 'Whole Foods',
                'pack_size' => '2 L',
                'cadence_days' => 7,
                'threshold' => 1,
                'stock_level' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Organic Whole Milk')
            ->assertJsonPath('data.is_due', true)
            ->assertJsonPath('data.due_reason', 'safety_stock')
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/v1/essentials/{$id}/restock", ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('data.stock_level', 3)
            ->assertJsonPath('data.is_due', false);

        $this->assertNotNull(
            $this->withBypass(fn () => Essential::query()->findOrFail($id)->last_restocked_at)
        );
    }

    public function test_an_item_becomes_due_again_when_the_cadence_elapses(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withBypass(fn () => Essential::create([
            'tenant_id' => $tenant->id,
            'name' => 'Coffee Beans',
            'cadence_days' => 7,
            'threshold' => 0,
            'stock_level' => 4,
            'last_restocked_at' => now()->subDays(8),
        ]));

        $payload = $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/essentials')
            ->assertOk()
            ->json();

        $this->assertTrue($payload['data'][0]['is_due']);
        $this->assertSame('cadence', $payload['data'][0]['due_reason']);
        $this->assertSame(1, $payload['summary']['due']);
        $this->assertSame(1, $payload['summary']['cadence']);
    }

    public function test_the_pulse_summary_counts_the_whole_pantry(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withBypass(function () use ($tenant): void {
            Essential::create([
                'tenant_id' => $tenant->id, 'name' => 'Milk',
                'stock_level' => 0, 'threshold' => 1, 'cadence_days' => 7,
            ]);
            Essential::create([
                'tenant_id' => $tenant->id, 'name' => 'Rice',
                'stock_level' => 5, 'threshold' => 1, 'cadence_days' => 30,
                'last_restocked_at' => now(),
            ]);
        });

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/essentials')
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.due', 1)
            ->assertJsonPath('summary.safety_stock', 1)
            ->assertJsonPath('summary.cadence', 0);
    }

    public function test_due_only_narrows_the_list_but_not_the_summary(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withBypass(function () use ($tenant): void {
            Essential::create([
                'tenant_id' => $tenant->id, 'name' => 'Milk',
                'stock_level' => 0, 'threshold' => 1, 'cadence_days' => 7,
            ]);
            Essential::create([
                'tenant_id' => $tenant->id, 'name' => 'Rice',
                'stock_level' => 5, 'threshold' => 1, 'cadence_days' => 30,
                'last_restocked_at' => now(),
            ]);
        });

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/essentials?due_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Milk')
            ->assertJsonPath('summary.total', 2);
    }

    public function test_a_junior_can_restock_but_not_reconfigure_the_pantry(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $junior = $this->createMember($tenant, 'junior');

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/essentials', ['name' => 'Dish Soap', 'stock_level' => 0])
            ->assertCreated();

        $juniorToken = $this->tokenFor($junior);

        $this->withToken($juniorToken)
            ->postJson('/api/v1/essentials', ['name' => 'Sneaky addition'])
            ->assertForbidden();

        $this->withToken($juniorToken)
            ->getJson('/api/v1/essentials')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_pantry_is_isolated_between_households(): void
    {
        $a = $this->createTenant('Household A');
        $b = $this->createTenant('Household B');
        $memberA = $this->createMember($a, 'member');
        $memberB = $this->createMember($b, 'member');

        $this->withBypass(fn () => Essential::create([
            'tenant_id' => $a->id, 'name' => 'A-only item', 'stock_level' => 2, 'threshold' => 0,
            'last_restocked_at' => now(), 'cadence_days' => 30,
        ]));

        $this->withToken($this->tokenFor($memberA))
            ->getJson('/api/v1/essentials')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($this->tokenFor($memberB))
            ->getJson('/api/v1/essentials')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Raw-DB barrier, not just the Eloquent scope.
        $this->withBypass(fn () => $this->assertDatabaseCount('essentials', 1));
    }
}
