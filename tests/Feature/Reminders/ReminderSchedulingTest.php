<?php

namespace Tests\Feature\Reminders;

use App\Models\Reminder;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ReminderSchedulingTest extends TestCase
{
    use CreatesTenants;

    public function test_creating_a_request_with_a_due_date_schedules_a_reminder(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $due = now()->addHours(3)->startOfSecond();

        $id = $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/requests', ['title' => 'Milk', 'due_at' => $due->toDateTimeString()])
            ->assertCreated()
            ->json('data.id');

        $this->withBypass(function () use ($id, $due): void {
            $this->assertDatabaseHas('reminders', [
                'request_id' => $id,
                'status' => 'pending',
                'type' => 'due',
            ]);

            $reminder = Reminder::withoutGlobalScope('tenant')->where('request_id', $id)->firstOrFail();
            $this->assertSame($due->toDateTimeString(), $reminder->fire_at->toDateTimeString());
        });
    }

    public function test_a_request_without_a_due_date_has_no_reminders(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/requests', ['title' => 'Milk'])
            ->assertCreated();

        $this->withBypass(fn () => $this->assertDatabaseCount('reminders', 0));
    }

    public function test_completing_a_request_cancels_its_pending_reminders(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $id = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Milk', 'due_at' => now()->addHour()->toDateTimeString()])
            ->json('data.id');

        $this->withToken($token)->postJson("/api/v1/requests/{$id}/accept")->assertOk();
        $this->withToken($token)->postJson("/api/v1/requests/{$id}/complete")->assertOk();

        $this->withBypass(function () use ($id): void {
            $this->assertDatabaseHas('reminders', ['request_id' => $id, 'status' => 'cancelled']);
            $this->assertSame(0, Reminder::withoutGlobalScope('tenant')
                ->where('request_id', $id)
                ->where('status', 'pending')
                ->count());
        });
    }

    public function test_snoozing_replaces_the_due_reminder_with_a_snooze_reminder(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $id = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Milk', 'due_at' => now()->subMinute()->toDateTimeString()])
            ->json('data.id');

        $until = now()->addHours(2)->startOfSecond();

        $this->withToken($token)
            ->postJson("/api/v1/requests/{$id}/snooze", ['until' => $until->toDateTimeString()])
            ->assertOk();

        $this->withBypass(function () use ($id, $until): void {
            $pending = Reminder::withoutGlobalScope('tenant')
                ->where('request_id', $id)
                ->where('status', 'pending')
                ->get();

            $this->assertCount(1, $pending);
            $this->assertSame('snooze', $pending->first()->type->value);
            $this->assertSame($until->toDateTimeString(), $pending->first()->fire_at->toDateTimeString());
        });
    }

    public function test_deferring_moves_the_reminder(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $id = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Milk', 'due_at' => now()->addHour()->toDateTimeString()])
            ->json('data.id');

        $newDue = now()->addDays(2)->startOfSecond();

        $this->withToken($token)
            ->patchJson("/api/v1/requests/{$id}", ['due_at' => $newDue->toDateTimeString()])
            ->assertOk();

        $this->withBypass(function () use ($id, $newDue): void {
            $reminder = Reminder::withoutGlobalScope('tenant')
                ->where('request_id', $id)
                ->where('status', 'pending')
                ->firstOrFail();

            $this->assertSame($newDue->toDateTimeString(), $reminder->fire_at->toDateTimeString());
        });
    }
}
