<?php

namespace Tests\Integration\Places;

use App\Models\LocationNotification;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The database-level barrier for arrival rows, plus the cross-household rule that
 * one tenant's place and tasks can never surface in another's arrival.
 */
class LocationNotificationIsolationTest extends TestCase
{
    use CreatesTenants;

    private const INSIDE = ['latitude' => 23.8105, 'longitude' => 90.4125];

    public function test_arrival_rows_carry_forced_row_level_security(): void
    {
        $flags = DB::selectOne(
            'select relrowsecurity, relforcerowsecurity from pg_class where relname = ?',
            ['location_notifications']
        );

        $this->assertTrue($flags->relrowsecurity, 'RLS is not enabled on location_notifications');
        $this->assertTrue($flags->relforcerowsecurity, 'RLS is not FORCED on location_notifications');
    }

    public function test_no_tenant_context_returns_no_arrival_rows(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $member->forceFill(['location_notifications_opt_in' => true])->save();

        $place = $this->createPlace($tenant, 'Whole Foods', ['category' => 'grocery']);

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Milk', 'place_id' => $place->id, 'assignee_id' => $member->id,
        ]));

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/geofences/evaluate', self::INSIDE)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'sent');

        DB::statement("select set_config('app.current_tenant', '', false)");
        DB::statement("select set_config('app.bypass_rls', 'off', false)");

        $this->assertSame(0, DB::table('location_notifications')->count());
    }

    public function test_another_households_place_never_surfaces(): void
    {
        $mine = $this->createTenant('Household A');
        $theirs = $this->createTenant('Household B');

        $me = $this->createMember($mine, 'member');
        $me->forceFill(['location_notifications_opt_in' => true])->save();
        $them = $this->createMember($theirs, 'member');

        // The same coordinates in both households: the position is identical, so
        // only tenant context can keep them apart.
        $theirPlace = $this->createPlace($theirs, 'Their Shop', ['category' => 'grocery']);

        $this->withBypass(fn () => $this->createRequest($theirs, $them, [
            'title' => 'Their milk', 'place_id' => $theirPlace->id, 'assignee_id' => $them->id,
        ]));

        $this->withToken($this->tokenFor($me))
            ->postJson('/api/v1/geofences/evaluate', self::INSIDE)
            ->assertOk()
            ->assertJsonCount(0, 'transitions')
            ->assertJsonCount(0, 'location_notifications')
            ->assertJsonCount(0, 'shopping_lists');

        $this->withBypass(function () use ($mine): void {
            $this->assertSame(0, LocationNotification::withoutGlobalScope('tenant')
                ->where('tenant_id', $mine->id)
                ->count());
        });
    }

    public function test_an_arrival_row_belongs_to_the_member_who_arrived(): void
    {
        $tenant = $this->createTenant('Household A');
        $one = $this->createMember($tenant, 'member');
        $two = $this->createMember($tenant, 'member');

        foreach ([$one, $two] as $member) {
            $member->forceFill(['location_notifications_opt_in' => true])->save();
        }

        $place = $this->createPlace($tenant, 'Shared shop', ['category' => 'grocery']);

        $this->withBypass(function () use ($tenant, $one, $two, $place): void {
            $this->createRequest($tenant, $one, ['title' => 'One', 'place_id' => $place->id, 'assignee_id' => $one->id]);
            $this->createRequest($tenant, $two, ['title' => 'Two', 'place_id' => $place->id, 'assignee_id' => $two->id]);
        });

        $this->withToken($this->tokenFor($one))
            ->postJson('/api/v1/geofences/evaluate', self::INSIDE)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.task_count', 1)
            ->assertJsonPath('location_notifications.0.tasks.0.title', 'One');

        $this->withBypass(function () use ($one): void {
            $rows = LocationNotification::withoutGlobalScope('tenant')->get();

            $this->assertSame(1, $rows->count());
            $this->assertSame($one->id, (int) $rows->first()->user_id);
        });
    }

    public function test_a_second_households_quiet_hours_do_not_apply(): void
    {
        $tenant = $this->createTenant('Household A');
        $other = $this->createTenant('Household B');

        $member = $this->createMember($tenant, 'member');
        $member->forceFill(['location_notifications_opt_in' => true])->save();

        $place = $this->createPlace($tenant, 'Whole Foods', ['category' => 'grocery']);

        $this->withBypass(function () use ($tenant, $member, $place, $other): void {
            $this->createRequest($tenant, $member, [
                'title' => 'Milk', 'place_id' => $place->id, 'assignee_id' => $member->id,
            ]);

            // Somebody else's quiet hours must not silence this household.
            Tenant::withoutGlobalScope('tenant')->whereKey($other->id)->update([
                'settings' => ['quiet_hours' => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']],
            ]);
        });

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/geofences/evaluate', self::INSIDE)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'sent');
    }
}
