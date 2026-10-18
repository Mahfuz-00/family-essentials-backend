<?php

namespace Tests\Feature\Geo;

use App\Models\Tenant;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Arrival notifications are opt-in, per member — the same shape as voice consent.
 */
class LocationNotificationPreferenceTest extends TestCase
{
    use CreatesTenants;

    private const INSIDE = ['latitude' => 23.8105, 'longitude' => 90.4125];

    protected function setUp(): void
    {
        parent::setUp();

        config(['location_notifications.cooldown_minutes' => 0]);
    }

    public function test_a_new_member_has_arrival_notifications_off(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->assertFalse((bool) $member->location_notifications_opt_in);

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.location_notifications_opt_in', false);
    }

    public function test_a_member_can_turn_them_on_and_off(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $this->withToken($token)
            ->patchJson('/api/v1/auth/location-consent', ['opt_in' => true])
            ->assertOk()
            ->assertJsonPath('user.location_notifications_opt_in', true);

        $this->withBypass(fn () => $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'location_notifications_opt_in' => true,
        ]));

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.location_notifications_opt_in', true);

        $this->withToken($token)
            ->patchJson('/api/v1/auth/location-consent', ['opt_in' => false])
            ->assertOk()
            ->assertJsonPath('user.location_notifications_opt_in', false);
    }

    public function test_the_mobile_surface_accepts_the_same_preference(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->patchJson('/mobile/v1/auth/location-consent', ['opt_in' => true])
            ->assertOk()
            ->assertJsonPath('user.location_notifications_opt_in', true);
    }

    public function test_the_preference_must_be_a_boolean(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->patchJson('/api/v1/auth/location-consent', ['opt_in' => 'yes please'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['opt_in']);
    }

    public function test_the_preference_is_per_member(): void
    {
        $tenant = $this->createTenant('Household A');
        $optedIn = $this->createMember($tenant, 'member');
        $optedIn->forceFill(['location_notifications_opt_in' => true])->save();

        $notOptedIn = $this->createMember($tenant, 'member');

        $place = $this->createPlace($tenant, 'Whole Foods', ['category' => 'grocery']);

        $this->withBypass(function () use ($tenant, $optedIn, $notOptedIn, $place): void {
            $this->createRequest($tenant, $optedIn, [
                'title' => 'Mine',
                'place_id' => $place->id,
                'assignee_id' => $optedIn->id,
                'location_trigger_place_id' => $place->id,
                'location_trigger_direction' => 'enter',
            ]);

            $this->createRequest($tenant, $notOptedIn, [
                'title' => 'Theirs',
                'place_id' => $place->id,
                'assignee_id' => $notOptedIn->id,
                'location_trigger_place_id' => $place->id,
                'location_trigger_direction' => 'enter',
            ]);
        });

        $this->withToken($this->tokenFor($optedIn))
            ->postJson('/api/v1/geofences/evaluate', self::INSIDE)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'sent');

        $this->withToken($this->tokenFor($notOptedIn))
            ->postJson('/api/v1/geofences/evaluate', self::INSIDE)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.status', 'skipped')
            ->assertJsonPath('location_notifications.0.reason', 'not_opted_in');
    }

    public function test_quiet_hours_still_apply_to_an_opted_in_member(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $member->forceFill(['location_notifications_opt_in' => true])->save();

        $place = $this->createPlace($tenant, 'Home', ['category' => 'home']);

        $this->withBypass(function () use ($tenant, $member, $place): void {
            $this->createRequest($tenant, $member, [
                'title' => 'Bins',
                'place_id' => $place->id,
                'assignee_id' => $member->id,
                'location_trigger_place_id' => $place->id,
                'location_trigger_direction' => 'enter',
            ]);

            Tenant::withoutGlobalScope('tenant')->whereKey($tenant->id)->update([
                'settings' => ['quiet_hours' => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']],
            ]);
        });

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/geofences/evaluate', self::INSIDE)
            ->assertOk()
            ->assertJsonPath('location_notifications.0.reason', 'quiet_hours');
    }
}
