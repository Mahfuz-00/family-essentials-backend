<?php

namespace Tests\Feature\Geo;

use App\Models\GeofenceEvent;
use App\Models\NotificationLog;
use App\Models\Place;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VoiceCallLog;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * The reminder cue: a saved place and a direction, chosen per task.
 *
 * "Buy chips" and "remind me when I leave the office" are different facts, so
 * these tests always link a shop *and* a trigger — and several assert that the
 * two are not confused.
 */
class LocationNotificationTest extends TestCase
{
    use CreatesTenants;

    /** Inside a 300 m fence centred on 23.8103, 90.4125. */
    private const INSIDE = ['latitude' => 23.8105, 'longitude' => 90.4125];

    /** Well outside it. */
    private const OUTSIDE = ['latitude' => 23.8300, 'longitude' => 90.4125];

    protected function setUp(): void
    {
        parent::setUp();

        // Most tests re-enter the same place; the cooldown has its own test.
        config(['location_notifications.cooldown_minutes' => 0]);
    }

    private function optedIn(User $user): User
    {
        $user->forceFill(['location_notifications_opt_in' => true])->save();

        return $user;
    }

    private function place(string $token, string $category, string $name = 'Whole Foods'): int
    {
        return (int) $this->withToken($token)
            ->postJson('/api/v1/places', [
                'name' => $name,
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'radius_meters' => 300,
                'category' => $category,
            ])
            ->assertCreated()
            ->json('data.id');
    }

    /**
     * @return array{0: int, 1: string, 2: User, 3: Tenant} placeId, token, member, tenant
     */
    private function household(string $category = 'grocery', string $name = 'Whole Foods'): array
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->optedIn($this->createMember($tenant, 'member'));
        $token = $this->tokenFor($member);
        $placeId = $this->place($token, $category, $name);

        return [$placeId, $token, $member, $tenant];
    }

    /**
     * A task linked to the shop *and* carrying its own reminder cue.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function task(
        Tenant $tenant,
        User $assignee,
        int $placeId,
        string $direction = 'enter',
        array $attributes = [],
    ): ServiceRequest {
        return $this->withBypass(fn (): ServiceRequest => $this->createRequest($tenant, $assignee, array_merge([
            'place_id' => $placeId,
            'assignee_id' => $assignee->id,
            'location_trigger_place_id' => $placeId,
            'location_trigger_direction' => $direction,
        ], $attributes)));
    }

    private function arrive(string $token, array $position = self::INSIDE)
    {
        return $this->withToken($token)->postJson('/api/v1/geofences/evaluate', $position);
    }

    // ------------------------------------------------------------- the cue

    public function test_leaving_work_tells_the_member_what_to_pick_up(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household('office', 'Office');

        $this->task($tenant, $member, $placeId, 'exit', ['title' => 'Milk']);
        $this->task($tenant, $member, $placeId, 'exit', ['title' => 'Bread']);

        // Arriving is not the cue; leaving is.
        $this->arrive($token)->assertOk()->assertJsonPath('location_notifications.0.reason', 'no_tasks');

        $this->arrive($token, self::OUTSIDE)
            ->assertOk()
            ->assertJsonPath('transitions.0.event', 'exit')
            ->assertJsonPath('location_notifications.0.status', 'sent')
            ->assertJsonPath('location_notifications.0.kind', 'office')
            ->assertJsonPath('location_notifications.0.body', 'Leaving work: 2 items to pick up.')
            ->assertJsonCount(2, 'location_notifications.0.tasks');

        $this->withBypass(fn () => $this->assertDatabaseHas('location_notifications', [
            'place_id' => $placeId,
            'user_id' => $member->id,
            'status' => 'sent',
        ]));
    }

    public function test_arriving_home_tells_the_member_what_is_still_outstanding(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household('home', 'Home');
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Bins out']);

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.kind', 'home')
            ->assertJsonPath('location_notifications.0.body', "You're home — 1 item still on the list.");
    }

    public function test_arriving_at_a_shop_names_it(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household('grocery', 'Whole Foods');
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Bread']);

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.body', "You're at Whole Foods: 2 items.");
    }

    public function test_a_cue_for_another_place_does_not_fire(): void
    {
        [$shopId, $token, $member, $tenant] = $this->household('grocery', 'Whole Foods');

        // The cue is on Home, which is nowhere near the shop.
        $home = (int) $this->withToken($token)->postJson('/api/v1/places', [
            'name' => 'Home',
            'latitude' => 23.8300,
            'longitude' => 90.4125,
            'radius_meters' => 300,
            'category' => 'home',
        ])->json('data.id');

        $this->task($tenant, $member, $home, 'enter', ['title' => 'Bins']);

        $this->arrive($token)
            ->assertOk()
            ->assertJsonCount(1, 'transitions')
            ->assertJsonPath('location_notifications.0.place_id', $shopId)
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'no_tasks');
    }

    public function test_an_entry_cue_does_not_fire_on_the_way_out(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->arrive($token)->assertOk()->assertJsonPath('location_notifications.0.status', 'sent');

        $this->arrive($token, self::OUTSIDE)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'no_tasks');
    }

    // ---------------------------------------------------------- who counts

    public function test_somebody_elses_tasks_are_not_counted(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $other = $this->createMember($tenant, 'member');

        $this->task($tenant, $other, $placeId, 'enter', ['title' => 'Not mine']);

        // The store list is everybody's; the reminder is personal.
        $this->arrive($token)
            ->assertOk()
            ->assertJsonCount(1, 'shopping_lists.0.requests')
            ->assertJsonPath('location_notifications.0.reason', 'no_tasks');
    }

    public function test_the_store_list_still_returns_every_open_request(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $other = $this->createMember($tenant, 'member');

        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Mine']);
        $this->task($tenant, $other, $placeId, 'enter', ['title' => 'Theirs']);

        $this->arrive($token)
            ->assertOk()
            ->assertJsonCount(2, 'shopping_lists.0.requests')
            ->assertJsonPath('location_notifications.0.task_count', 1);
    }

    public function test_finished_and_cancelled_tasks_stop_counting(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();

        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Done', 'status' => 'completed', 'completed_at' => now()]);
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Dropped', 'status' => 'cancelled']);
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Refused', 'status' => 'declined']);

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'no_tasks');
    }

    public function test_a_task_reassigned_away_stops_counting(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $other = $this->createMember($tenant, 'member');

        $request = $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->arrive($token)->assertOk()->assertJsonPath('location_notifications.0.status', 'sent');

        $this->withBypass(fn () => $request->forceFill(['assignee_id' => $other->id])->save());

        $this->arrive($token, self::OUTSIDE)->assertOk();

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.reason', 'no_tasks');
    }

    // ------------------------------------------------------------ dedupe

    public function test_staying_inside_does_not_notify_again(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->arrive($token)->assertOk()->assertJsonCount(1, 'location_notifications');

        $this->arrive($token)
            ->assertOk()
            ->assertJsonCount(0, 'transitions')
            ->assertJsonCount(0, 'location_notifications');

        $this->withBypass(fn () => $this->assertSame(1, \App\Models\LocationNotification::withoutGlobalScope('tenant')->count()));
    }

    public function test_a_second_cue_inside_the_cooldown_is_refused(): void
    {
        config(['location_notifications.cooldown_minutes' => 60]);

        [$placeId, $token, $member, $tenant] = $this->household();
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->arrive($token)->assertOk()->assertJsonPath('location_notifications.0.status', 'sent');
        $this->arrive($token, self::OUTSIDE)->assertOk();

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'cooldown');
    }

    public function test_a_cue_after_the_cooldown_notifies_again(): void
    {
        config(['location_notifications.cooldown_minutes' => 60]);

        [$placeId, $token, $member, $tenant] = $this->household();
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->arrive($token)->assertOk();

        $this->withBypass(fn () => \App\Models\LocationNotification::withoutGlobalScope('tenant')
            ->where('status', 'sent')
            ->update(['notified_at' => now()->subHours(2)]));

        $this->arrive($token, self::OUTSIDE)->assertOk();

        $this->arrive($token)->assertOk()->assertJsonPath('location_notifications.0.status', 'sent');
    }

    public function test_each_transition_produces_at_most_one_decision(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->arrive($token)->assertOk();
        $this->arrive($token, self::OUTSIDE)->assertOk();
        $this->arrive($token)->assertOk();

        $this->withBypass(function (): void {
            $rows = \App\Models\LocationNotification::withoutGlobalScope('tenant')->get();

            // Three transitions (enter, exit, enter): one decision each. The exit
            // is a recorded refusal because the cue was set to entry.
            $this->assertSame(3, $rows->count());
            $this->assertSame(3, $rows->pluck('geofence_event_id')->unique()->count());
            $this->assertSame(2, $rows->filter(fn ($row): bool => $row->isSent())->count());
        });
    }

    public function test_a_time_reminder_suppresses_the_location_nudge(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $task = $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        // The deadline reminder already went out minutes ago.
        $this->withBypass(fn () => NotificationLog::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'request_id' => $task->getKey(),
            'user_id' => $member->id,
            'channel' => 'push',
            'event' => 'reminder.due',
            'status' => 'sent',
            'message' => 'Reminder: Milk',
            'sent_at' => now()->subMinutes(5),
        ]));

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'already_nudged');
    }

    public function test_an_old_time_reminder_does_not_suppress_it(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $task = $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->withBypass(fn () => NotificationLog::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'request_id' => $task->getKey(),
            'user_id' => $member->id,
            'channel' => 'push',
            'event' => 'reminder.due',
            'status' => 'sent',
            'message' => 'Reminder: Milk',
            'sent_at' => now()->subHours(4),
        ]));

        $this->arrive($token)->assertOk()->assertJsonPath('location_notifications.0.status', 'sent');
    }

    // ----------------------------------------------------------- the gates

    public function test_quiet_hours_hold_the_notification_back(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->withBypass(fn () => Tenant::withoutGlobalScope('tenant')->whereKey($tenant->id)->update([
            'settings' => ['quiet_hours' => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']],
        ]));

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.reason', 'quiet_hours');
    }

    public function test_a_member_who_has_not_opted_in_is_told_nothing(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);
        $placeId = $this->place($token, 'grocery');

        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.reason', 'not_opted_in');
    }

    public function test_the_feature_can_be_switched_off_entirely(): void
    {
        config(['location_notifications.enabled' => false]);

        [$placeId, $token, $member, $tenant] = $this->household();
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.reason', 'disabled');
    }

    public function test_a_cue_never_places_a_voice_call(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->arrive($token)->assertOk()->assertJsonPath('location_notifications.0.status', 'sent');

        $this->withBypass(fn () => $this->assertSame(0, VoiceCallLog::withoutGlobalScope('tenant')->count()));
    }

    public function test_an_entry_from_days_ago_does_not_suppress_the_next_visit(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Milk']);

        $this->withBypass(fn () => GeofenceEvent::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'place_id' => $placeId,
            'user_id' => $member->id,
            'event' => 'enter',
            'occurred_at' => now()->subHours(30),
        ]));

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('transitions.0.event', 'enter')
            ->assertJsonPath('location_notifications.0.status', 'sent');
    }

    // ------------------------------------------------------- compatibility

    public function test_a_request_without_a_trigger_is_untouched(): void
    {
        [, $token, $member, $tenant] = $this->household();

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Batteries', 'store_name' => 'Corner Shop', 'assignee_id' => $member->id,
        ]));

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.reason', 'no_tasks');

        $this->withBypass(fn () => $this->assertSame(1, ServiceRequest::withoutGlobalScope('tenant')
            ->whereNull('place_id')
            ->count()));
    }

    public function test_a_place_with_no_category_still_works(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->optedIn($this->createMember($tenant, 'member'));
        $token = $this->tokenFor($member);

        $placeId = (int) $this->withToken($token)
            ->postJson('/api/v1/places', [
                'name' => 'Somewhere',
                'latitude' => 23.8103,
                'longitude' => 90.4125,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->task($tenant, $member, $placeId, 'enter', ['title' => 'Odd job']);

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.kind', 'other')
            ->assertJsonPath('location_notifications.0.body', 'At Somewhere: 1 task.');
    }

    public function test_two_places_in_range_produce_one_decision_each(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->optedIn($this->createMember($tenant, 'member'));
        $token = $this->tokenFor($member);

        $shop = (int) $this->withToken($token)->postJson('/api/v1/places', [
            'name' => 'Shop', 'latitude' => 23.8103, 'longitude' => 90.4125, 'radius_meters' => 300, 'category' => 'grocery',
        ])->json('data.id');

        $home = (int) $this->withToken($token)->postJson('/api/v1/places', [
            'name' => 'Home', 'latitude' => 23.8104, 'longitude' => 90.4126, 'radius_meters' => 300, 'category' => 'home',
        ])->json('data.id');

        $this->task($tenant, $member, $shop, 'enter', ['title' => 'Milk']);
        $this->task($tenant, $member, $home, 'enter', ['title' => 'Bins']);

        $this->arrive($token)->assertOk()->assertJsonCount(2, 'location_notifications');
    }

    // ------------------------------------------------- trigger through the API

    public function test_a_request_can_carry_its_own_trigger(): void
    {
        [$placeId, $token] = $this->household('office', 'Office');

        $payload = $this->withToken($token)
            ->postJson('/api/v1/requests', [
                'title' => 'Pick up the parcel',
                'location_trigger_place_id' => $placeId,
                'location_trigger_direction' => 'exit',
            ])
            ->assertCreated()
            ->assertJsonPath('data.location_trigger.place_id', $placeId)
            ->assertJsonPath('data.location_trigger.direction', 'exit')
            ->json();

        $id = $payload['data']['id'];

        // Clearing it must be possible, and must clear both halves.
        $this->withToken($token)
            ->patchJson("/api/v1/requests/{$id}", [
                'location_trigger_place_id' => null,
                'location_trigger_direction' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.location_trigger', null);
    }

    public function test_a_direction_without_a_place_is_rejected(): void
    {
        [, $token] = $this->household();

        $this->withToken($token)
            ->postJson('/api/v1/requests', [
                'title' => 'Something',
                'location_trigger_direction' => 'exit',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_trigger_direction']);
    }

    public function test_a_place_without_a_direction_is_rejected(): void
    {
        [$placeId, $token] = $this->household();

        $this->withToken($token)
            ->postJson('/api/v1/requests', [
                'title' => 'Something',
                'location_trigger_place_id' => $placeId,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_trigger_direction']);
    }

    public function test_another_households_place_cannot_be_a_trigger(): void
    {
        [, $token] = $this->household();

        $other = $this->createTenant('Household B');
        $otherMember = $this->createMember($other, 'member');
        $theirPlace = $this->place($this->tokenFor($otherMember), 'grocery', 'Their Shop');

        $this->withToken($token)
            ->postJson('/api/v1/requests', [
                'title' => 'Sneaky',
                'location_trigger_place_id' => $theirPlace,
                'location_trigger_direction' => 'enter',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_trigger_place_id']);
    }

    public function test_a_place_is_reported_with_its_kind(): void
    {
        [, $token] = $this->household('office', 'Downtown Office');

        $this->withToken($token)
            ->getJson('/api/v1/places')
            ->assertOk()
            ->assertJsonPath('data.0.kind', 'office')
            ->assertJsonPath('data.0.kind_label', 'Office')
            ->assertJsonPath('data.0.category', 'office');
    }

    public function test_places_are_still_listed_and_edited(): void
    {
        [, $token, , $tenant] = $this->household();

        $this->withToken($token)->getJson('/api/v1/places')->assertOk()->assertJsonCount(1, 'data');

        $place = $this->withBypass(fn () => Place::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail());

        $this->withToken($token)
            ->patchJson("/api/v1/places/{$place->id}", ['radius_meters' => 500])
            ->assertOk()
            ->assertJsonPath('data.radius_meters', 500);
    }
}
