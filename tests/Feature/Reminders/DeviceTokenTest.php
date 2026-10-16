<?php

namespace Tests\Feature\Reminders;

use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use CreatesTenants;

    public function test_a_member_can_register_a_device_token(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/device-tokens', ['token' => 'fcm-token-abc', 'platform' => 'android'])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android');

        $this->withBypass(fn () => $this->assertDatabaseHas('device_tokens', [
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'token' => 'fcm-token-abc',
        ]));
    }

    public function test_registering_the_same_token_twice_does_not_duplicate_it(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $this->withToken($token)->postJson('/api/v1/device-tokens', ['token' => 'dup', 'platform' => 'ios'])->assertCreated();
        $this->withToken($token)->postJson('/api/v1/device-tokens', ['token' => 'dup', 'platform' => 'ios'])->assertCreated();

        $this->withBypass(fn () => $this->assertDatabaseCount('device_tokens', 1));
    }

    public function test_a_member_can_revoke_their_own_token_but_not_anothers(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $other = $this->createMember($tenant, 'member');

        $id = $this->withToken($this->tokenFor($other))
            ->postJson('/api/v1/device-tokens', ['token' => 'other-device', 'platform' => 'android'])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($this->tokenFor($member))
            ->deleteJson("/api/v1/device-tokens/{$id}")
            ->assertForbidden();

        $this->withToken($this->tokenFor($other))
            ->deleteJson("/api/v1/device-tokens/{$id}")
            ->assertOk();

        $this->withBypass(fn () => $this->assertDatabaseCount('device_tokens', 0));
    }

    public function test_the_notification_feed_returns_the_callers_logs(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withBypass(fn () => \App\Models\NotificationLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'channel' => 'push',
            'event' => 'reminder.due',
            'title' => 'Reminder sent',
            'status' => 'sent',
            'provider' => 'log',
            'sent_at' => now(),
        ]));

        $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'reminder.due');
    }
}
