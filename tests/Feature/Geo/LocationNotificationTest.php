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
 * Arrival notifications: who is told what when they walk into a saved place with
 * their own work waiting, and why they are told nothing when they are not.
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

    private function placeWith(string $token, string $category, string $name = 'Whole Foods'): int
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
        $placeId = $this->placeWith($token, $category, $name);

        return [$placeId, $token, $member, $tenant];
    }

    private function arrive(string $token, array $position = self::INSIDE)
    {
        return $this->withToken($token)->postJson('/api/v1/geofences/evaluate', $position);
    }

    public function test_an_arrival_tells_the_member_about_their_own_open_tasks(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();

        $this->withBypass(function () use ($tenant, $member, $placeId): void {
            $this->createRequest($tenant, $member, ['title' => 'Milk', 'place_id' => $placeId, 'assignee_id' => $member->id]);
            $this->createRequest($tenant, $member, ['title' => 'Bread', 'place_id' => $placeId, 'assignee_id' => $member->id]);
        });

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'sent')
            ->assertJsonPath('location_notifications.0.kind', 'store')
            ->assertJsonPath('location_notifications.0.place_name', 'Whole Foods')
            ->assertJsonPath('location_notifications.0.task_count', 2)
            ->assertJsonPath('location_notifications.0.body', 'You have 2 items to buy here.')
            // Grouped into one notification, so the list of what it covers is
            // attached rather than sent as separate pushes.
            ->assertJsonCount(2, 'location_notifications.0.tasks')
            ->assertJsonPath('location_notifications.0.tasks.0.title', 'Milk');

        $this->withBypass(function () use ($placeId, $member): void {
            $this->assertDatabaseHas('location_notifications', [
                'place_id' => $placeId,
                'user_id' => $member->id,
                'status' => 'sent',
                'kind' => 'store',
                'task_count' => 2,
            ]);

            $log = NotificationLog::withoutGlobalScope('tenant')
                ->where('user_id', $member->id)
                ->where('event', 'location.arrival')
                ->firstOrFail();

            $this->assertSame('sent', $log->status->value);
            $this->assertSame('push', $log->channel->value);
        });
    }

    public function test_a_home_arrival_reads_differently(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household('home', 'Home');

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Bins out',
            'place_id' => $placeId,
            'assignee_id' => $member->id,
        ]));

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.kind', 'home')
            ->assertJsonPath('location_notifications.0.body', 'You have 1 household task to take care of.');
    }

    public function test_an_office_arrival_reads_differently(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household('office', 'Downtown Office');

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Return the laptop',
            'place_id' => $placeId,
            'assignee_id' => $member->id,
        ]));

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.kind', 'office')
            ->assertJsonPath('location_notifications.0.body', 'You have 1 task associated with work.');
    }

    public function test_somebody_elses_tasks_are_not_counted(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $other = $this->createMember($tenant, 'member');

        $this->withBypass(fn () => $this->createRequest($tenant, $other, [
            'title' => 'Not mine',
            'place_id' => $placeId,
            'assignee_id' => $other->id,
        ]));

        // The store list is everybody's; a notification is personal.
        $this->arrive($token)
            ->assertOk()
            ->assertJsonCount(1, 'shopping_lists.0.requests')
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'no_tasks');
    }

    public function test_the_store_list_still_returns_every_open_request(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $other = $this->createMember($tenant, 'member');

        $this->withBypass(function () use ($tenant, $member, $other, $placeId): void {
            $this->createRequest($tenant, $member, ['title' => 'Mine', 'place_id' => $placeId, 'assignee_id' => $member->id]);
            $this->createRequest($tenant, $other, ['title' => 'Theirs', 'place_id' => $placeId, 'assignee_id' => $other->id]);
        });

        // Unchanged behaviour: the lists were never filtered by assignee.
        $this->arrive($token)
            ->assertOk()
            ->assertJsonCount(2, 'shopping_lists.0.requests')
            ->assertJsonPath('location_notifications.0.task_count', 1);
    }

    public function test_finished_and_cancelled_tasks_stop_counting(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();

        $this->withBypass(function () use ($tenant, $member, $placeId): void {
            $this->createRequest($tenant, $member, ['title' => 'Done', 'place_id' => $placeId, 'assignee_id' => $member->id, 'status' => 'completed', 'completed_at' => now()]);
            $this->createRequest($tenant, $member, ['title' => 'Dropped', 'place_id' => $placeId, 'assignee_id' => $member->id, 'status' => 'cancelled']);
            $this->createRequest($tenant, $member, ['title' => 'Refused', 'place_id' => $placeId, 'assignee_id' => $member->id, 'status' => 'declined']);
        });

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'no_tasks');
    }

    public function test_a_task_reassigned_away_stops_counting(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();
        $other = $this->createMember($tenant, 'member');

        $request = $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Milk',
            'place_id' => $placeId,
            'assignee_id' => $member->id,
        ]));

        $this->arrive($token)->assertOk()->assertJsonPath('location_notifications.0.status', 'sent');

        $this->withBypass(fn () => $request->forceFill(['assignee_id' => $other->id])->save());

        $this->arrive($token, self::OUTSIDE)->assertOk();

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'no_tasks');
    }

    public function test_staying_inside_does_not_notify_again(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Milk', 'place_id' => $placeId, 'assignee_id' => $member->id,
        ]));

        $this->arrive($token)->assertOk()->assertJsonCount(1, 'location_notifications');

        // A second report from inside the same fence is not an arrival.
        $this->arrive($token)
            ->assertOk()
            ->assertJsonCount(0, 'transitions')
            ->assertJsonCount(0, 'location_notifications');

        $this->withBypass(fn () => $this->assertSame(1, \App\Models\LocationNotification::withoutGlobalScope('tenant')->count()));
    }

    public function test_a_second_arrival_inside_the_cooldown_is_refused_with_a_reason(): void
    {
        config(['location_notifications.cooldown_minutes' => 60]);

        [$placeId, $token, $member, $tenant] = $this->household();

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Milk', 'place_id' => $placeId, 'assignee_id' => $member->id,
        ]));

        $this->arrive($token)->assertOk()->assertJsonPath('location_notifications.0.status', 'sent');

        $this->arrive($token, self::OUTSIDE)->assertOk();

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'cooldown')
            ->assertJsonPath('location_notifications.0.reason_message', 'You were already told about this place recently.');
    }

    public function test_an_arrival_after_the_cooldown_notifies_again(): void
    {
        config(['location_notifications.cooldown_minutes' => 60]);

        [$placeId, $token, $member, $tenant] = $this->household();

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Milk', 'place_id' => $placeId, 'assignee_id' => $member->id,
        ]));

        $this->arrive($token)->assertOk();

        // The earlier notification is older than the cooldown now.
        $this->withBypass(fn () => \App\Models\LocationNotification::withoutGlobalScope('tenant')
            ->where('status', 'sent')
            ->update(['notified_at' => now()->subHours(2)]));

        $this->arrive($token, self::OUTSIDE)->assertOk();

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'sent');
    }

    public function test_quiet_hours_hold_the_notification_back(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Milk', 'place_id' => $placeId, 'assignee_id' => $member->id,
        ]));

        $this->withBypass(fn () => Tenant::withoutGlobalScope('tenant')->whereKey($tenant->id)->update([
            'settings' => ['quiet_hours' => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']],
        ]));

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'quiet_hours');
    }

    public function test_a_member_who_has_not_opted_in_is_told_nothing(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $placeId = $this->placeWith($token, 'grocery');

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Milk', 'place_id' => $placeId, 'assignee_id' => $member->id,
        ]));

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'not_opted_in');

        $this->withBypass(fn () => $this->assertSame(0, NotificationLog::withoutGlobalScope('tenant')
            ->where('event', 'location.arrival')
            ->where('status', 'sent')
            ->count()));
    }

    public function test_the_feature_can_be_switched_off_entirely(): void
    {
        config(['location_notifications.enabled' => false]);

        [$placeId, $token, $member, $tenant] = $this->household();

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Milk', 'place_id' => $placeId, 'assignee_id' => $member->id,
        ]));

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'disabled');
    }

    public function test_an_arrival_never_places_a_voice_call(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Milk', 'place_id' => $placeId, 'assignee_id' => $member->id,
        ]));

        $this->arrive($token)->assertOk()->assertJsonPath('location_notifications.0.status', 'sent');

        // Arriving is not an escalation, whatever the priority.
        $this->withBypass(fn () => $this->assertSame(0, VoiceCallLog::withoutGlobalScope('tenant')->count()));
    }

    public function test_an_entry_from_days_ago_does_not_suppress_the_next_visit(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Milk', 'place_id' => $placeId, 'assignee_id' => $member->id,
        ]));

        // A client that stopped reporting never wrote the matching exit. Without
        // the staleness cutoff this would read as "still inside" and today's
        // visit would produce no arrival at all.
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

    public function test_a_request_without_a_place_is_untouched(): void
    {
        [, $token, $member, $tenant] = $this->household();

        // The old shape: free text, no saved location.
        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Batteries', 'store_name' => 'Corner Shop', 'assignee_id' => $member->id,
        ]));

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
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

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Odd job', 'place_id' => $placeId, 'assignee_id' => $member->id,
        ]));

        $this->arrive($token)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.kind', 'other')
            ->assertJsonPath('location_notifications.0.body', 'You have 1 task here.');
    }

    public function test_two_places_in_range_produce_one_decision_each(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->optedIn($this->createMember($tenant, 'member'));
        $token = $this->tokenFor($member);

        // Two overlapping fences around the same reported position.
        $shop = (int) $this->withToken($token)->postJson('/api/v1/places', [
            'name' => 'Shop', 'latitude' => 23.8103, 'longitude' => 90.4125, 'radius_meters' => 300, 'category' => 'grocery',
        ])->json('data.id');

        $home = (int) $this->withToken($token)->postJson('/api/v1/places', [
            'name' => 'Home', 'latitude' => 23.8104, 'longitude' => 90.4126, 'radius_meters' => 300, 'category' => 'home',
        ])->json('data.id');

        $this->withBypass(function () use ($tenant, $member, $shop, $home): void {
            $this->createRequest($tenant, $member, ['title' => 'Milk', 'place_id' => $shop, 'assignee_id' => $member->id]);
            $this->createRequest($tenant, $member, ['title' => 'Bins', 'place_id' => $home, 'assignee_id' => $member->id]);
        });

        $this->arrive($token)
            ->assertOk()
            ->assertJsonCount(2, 'location_notifications');

        $this->withBypass(fn () => $this->assertSame(2, \App\Models\LocationNotification::withoutGlobalScope('tenant')->count()));
    }

    public function test_a_repeated_report_cannot_notify_twice(): void
    {
        [$placeId, $token, $member, $tenant] = $this->household();

        $this->withBypass(fn () => $this->createRequest($tenant, $member, [
            'title' => 'Milk', 'place_id' => $placeId, 'assignee_id' => $member->id,
        ]));

        // Enter, leave, and enter again inside the cooldown window of zero: the
        // unique key is what stops the second decision from notifying.
        config(['location_notifications.cooldown_minutes' => 0]);

        $this->arrive($token)->assertOk();
        $this->arrive($token, self::OUTSIDE)->assertOk();
        $this->arrive($token)->assertOk();

        $this->withBypass(function (): void {
            $rows = \App\Models\LocationNotification::withoutGlobalScope('tenant')->get();

            // One sent and one refused (cooldown of 0 means it is not refused, so
            // both entries are sent) — either way, never two rows for one entry.
            $this->assertSame(2, $rows->count());
            $this->assertSame(2, $rows->pluck('geofence_event_id')->unique()->count());
        });
    }

    public function test_a_member_with_no_places_arrives_to_nothing(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->optedIn($this->createMember($tenant, 'member'));

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/geofences/evaluate', self::INSIDE)
            ->assertOk()
            ->assertJsonCount(0, 'transitions')
            ->assertJsonCount(0, 'location_notifications')
            ->assertJsonCount(0, 'shopping_lists');
    }

    public function test_a_place_is_reported_with_its_kind(): void
    {
        [, $token] = $this->household('office', 'Downtown Office');

        $this->withToken($token)
            ->getJson('/api/v1/places')
            ->assertOk()
            ->assertJsonPath('data.0.kind', 'office')
            ->assertJsonPath('data.0.kind_label', 'Office')
            // The category itself is unchanged — no data migration happened.
            ->assertJsonPath('data.0.category', 'office');
    }

    public function test_places_are_still_listed_and_edited(): void
    {
        [, $token, , $tenant] = $this->household();

        $this->withToken($token)
            ->getJson('/api/v1/places')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $place = $this->withBypass(fn () => Place::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail());

        $this->withToken($token)
            ->patchJson("/api/v1/places/{$place->id}", ['radius_meters' => 500])
            ->assertOk()
            ->assertJsonPath('data.radius_meters', 500);
    }
}
