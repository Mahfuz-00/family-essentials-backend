<?php

namespace Tests\Feature\Recurring;

use App\Enums\Cadence;
use App\Enums\GeofenceEventType;
use App\Enums\RequestPriority;
use App\Models\RecurringRule;
use App\Models\ServiceRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The daily spawn command.
 *
 * Every test freezes "today", because the interesting behaviour is about the
 * relationship between a rule's clock and today's date — not about the calendar
 * on the machine running the suite.
 */
class SpawnRecurringRequestsTest extends TestCase
{
    use CreatesTenants;

    /** A Sunday, so the weekly case has to do arithmetic. */
    private const TODAY = '2026-10-18 07:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::TODAY);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_daily_rule_spawns_a_fresh_request_and_advances_one_day(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $rule = $this->createRecurringRule($tenant, $admin, [
            'title' => 'Water the plants',
            'quantity' => 'all of them',
            'priority' => RequestPriority::Low,
            'next_run_on' => '2026-10-18',
        ]);

        $this->artisan('essentials:spawn-recurring')->assertSuccessful();

        $this->withBypass(function () use ($rule, $admin, $tenant): void {
            $request = ServiceRequest::withoutGlobalScope('tenant')
                ->where('recurring_rule_id', $rule->id)
                ->sole();

            $this->assertSame('Water the plants', $request->title);
            $this->assertSame('all of them', $request->quantity);
            $this->assertSame('pending', $request->status->value);
            $this->assertSame($admin->id, $request->requested_by);
            $this->assertSame($tenant->id, $request->tenant_id);
            $this->assertSame('2026-10-18', $request->recurring_for?->toDateString());

            $fresh = RecurringRule::withoutGlobalScope('tenant')->findOrFail($rule->id);
            $this->assertSame('2026-10-19', $fresh->next_run_on->toDateString());
            $this->assertSame('2026-10-18', $fresh->last_run_on?->toDateString());
        });
    }

    public function test_a_weekly_rule_moves_seven_days_from_its_weekday(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        // Wednesday, from a Sunday.
        $rule = $this->createRecurringRule($tenant, $admin, [
            'cadence' => Cadence::Weekly,
            'weekday' => 3,
            'next_run_on' => '2026-10-18',
        ]);

        $this->artisan('essentials:spawn-recurring')->assertSuccessful();

        $this->withBypass(function () use ($rule): void {
            $fresh = RecurringRule::withoutGlobalScope('tenant')->findOrFail($rule->id);

            $this->assertSame('2026-10-21', $fresh->next_run_on->toDateString());
            $this->assertSame(3, $fresh->next_run_on->dayOfWeek);
            $this->assertSame(1, ServiceRequest::withoutGlobalScope('tenant')
                ->where('recurring_rule_id', $rule->id)->count());
        });
    }

    public function test_a_monthly_rule_clamps_a_short_month_and_returns_to_the_thirty_first(): void
    {
        Carbon::setTestNow('2026-02-28 07:00:00');

        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $rule = $this->createRecurringRule($tenant, $admin, [
            'cadence' => Cadence::Monthly,
            'day_of_month' => 31,
            'next_run_on' => '2026-02-28',
        ]);

        $this->artisan('essentials:spawn-recurring')->assertSuccessful();

        $this->withBypass(function () use ($rule): void {
            $request = ServiceRequest::withoutGlobalScope('tenant')
                ->where('recurring_rule_id', $rule->id)
                ->sole();

            $this->assertSame('2026-02-28', $request->recurring_for?->toDateString());

            // February has no 31st, so this cycle runs on the 28th and the next
            // one is the 31st of March.
            $fresh = RecurringRule::withoutGlobalScope('tenant')->findOrFail($rule->id);
            $this->assertSame('2026-03-31', $fresh->next_run_on->toDateString());
        });
    }

    public function test_a_second_run_on_the_same_day_spawns_nothing(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $rule = $this->createRecurringRule($tenant, $admin, ['next_run_on' => '2026-10-18']);

        $this->artisan('essentials:spawn-recurring')->assertSuccessful();
        $this->artisan('essentials:spawn-recurring')->assertSuccessful();

        // And again after pretending the first run died before it moved the
        // clock: the request for that cycle already exists, so it is not rebuilt.
        $this->withBypass(fn () => RecurringRule::withoutGlobalScope('tenant')
            ->whereKey($rule->id)
            ->update(['next_run_on' => '2026-10-18']));

        $this->artisan('essentials:spawn-recurring')->assertSuccessful();

        $this->withBypass(function () use ($rule): void {
            $this->assertSame(
                1,
                ServiceRequest::withoutGlobalScope('tenant')->where('recurring_rule_id', $rule->id)->count(),
            );

            $fresh = RecurringRule::withoutGlobalScope('tenant')->findOrFail($rule->id);
            $this->assertSame('2026-10-19', $fresh->next_run_on->toDateString());
        });
    }

    public function test_the_unique_index_makes_a_duplicate_cycle_impossible(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $rule = $this->createRecurringRule($tenant, $admin, ['next_run_on' => '2026-10-18']);

        $this->artisan('essentials:spawn-recurring')->assertSuccessful();

        $connection = DB::connection('pgsql');
        // A real tenant context, so the insert is refused by the index under test
        // and not by row-level security.
        $connection->statement("select set_config('app.current_tenant', ?, false)", [(string) $tenant->id]);
        $connection->statement("select set_config('app.bypass_rls', 'off', false)");
        $connection->beginTransaction();

        try {
            $connection->table('requests')->insert([
                'tenant_id' => $tenant->id,
                'title' => 'A second cycle for the same day',
                'requested_by' => $admin->id,
                'status' => 'pending',
                'priority' => 'normal',
                'recurring_rule_id' => $rule->id,
                'recurring_for' => '2026-10-18',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('Expected the unique index on (recurring_rule_id, recurring_for) to reject this row.');
        } catch (QueryException $exception) {
            // The index, not the command's manners, is what makes a retried run safe.
            $this->assertSame('23505', (string) $exception->getCode());
        } finally {
            $connection->rollBack();
        }
    }

    public function test_missed_cycles_are_skipped_rather_than_back_filled(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $rule = $this->createRecurringRule($tenant, $admin, ['next_run_on' => '2026-10-08']);

        $this->artisan('essentials:spawn-recurring')->assertSuccessful();

        $this->withBypass(function () use ($rule): void {
            $requests = ServiceRequest::withoutGlobalScope('tenant')
                ->where('recurring_rule_id', $rule->id)
                ->get();

            // Ten missed days produce one chore, not ten.
            $this->assertCount(1, $requests);
            $this->assertSame('2026-10-08', $requests->first()->recurring_for?->toDateString());

            $fresh = RecurringRule::withoutGlobalScope('tenant')->findOrFail($rule->id);
            $this->assertSame('2026-10-19', $fresh->next_run_on->toDateString());
            $this->assertTrue($fresh->next_run_on->isFuture());
        });
    }

    public function test_a_paused_rule_spawns_nothing(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $rule = $this->createRecurringRule($tenant, $admin, [
            'next_run_on' => '2026-10-01',
            'active' => false,
        ]);

        $this->artisan('essentials:spawn-recurring')->assertSuccessful();

        $this->withBypass(function () use ($rule): void {
            $this->assertSame(0, ServiceRequest::withoutGlobalScope('tenant')
                ->where('recurring_rule_id', $rule->id)->count());

            $fresh = RecurringRule::withoutGlobalScope('tenant')->findOrFail($rule->id);
            $this->assertSame('2026-10-01', $fresh->next_run_on->toDateString());
            $this->assertNull($fresh->last_run_on);
        });
    }

    public function test_the_spawned_request_reaches_the_board_with_the_rules_trigger_fields(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $sarah = $this->createMember($tenant, 'member');

        $shop = $this->createPlace($tenant, 'Whole Foods Market');
        $home = $this->createPlace($tenant, 'Home');

        $rule = $this->createRecurringRule($tenant, $admin, [
            'title' => 'Weekly shop',
            'category' => 'grocery',
            'priority' => RequestPriority::Normal,
            'assignee_id' => $sarah->id,
            'place_id' => $shop->id,
            'location_trigger_place_id' => $home->id,
            'location_trigger_direction' => GeofenceEventType::Exit,
            'next_run_on' => '2026-10-18',
        ]);

        $this->artisan('essentials:spawn-recurring')->assertSuccessful();

        $board = $this->withToken($this->tokenFor($sarah))
            ->getJson('/api/v1/requests/board')
            ->assertOk();

        $this->assertSame(1, $board->json('counts.open'));

        $spawned = collect($board->json('assigned_to_me'))->firstWhere('title', 'Weekly shop');

        $this->assertNotNull($spawned, 'The spawned request did not reach the board.');
        $this->assertSame('grocery', $spawned['category']);
        $this->assertSame('pending', $spawned['status']);
        $this->assertSame($shop->id, $spawned['place_id']);
        $this->assertSame($home->id, $spawned['location_trigger']['place_id']);
        $this->assertSame('exit', $spawned['location_trigger']['direction']);
        $this->assertSame($rule->id, $spawned['recurring_rule_id']);
        $this->assertSame('2026-10-18', $spawned['recurring_for']);
    }

    public function test_a_rule_that_is_not_due_is_left_alone(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $rule = $this->createRecurringRule($tenant, $admin, ['next_run_on' => '2026-11-01']);

        $this->artisan('essentials:spawn-recurring')->assertSuccessful();

        $this->withBypass(fn () => $this->assertSame(
            0,
            ServiceRequest::withoutGlobalScope('tenant')->where('recurring_rule_id', $rule->id)->count(),
        ));
    }
}
