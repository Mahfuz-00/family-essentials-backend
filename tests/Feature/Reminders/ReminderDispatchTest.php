<?php

namespace Tests\Feature\Reminders;

use App\Jobs\DispatchReminder;
use App\Models\NotificationLog;
use App\Models\Reminder;
use App\Models\Tenant;
use App\Notifications\Channels\PushSender;
use App\Reminders\ReminderDispatcher;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ReminderDispatchTest extends TestCase
{
    use CreatesTenants;

    private function dueRequestToken(): array
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $id = $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/requests', [
                'title' => 'Milk',
                'quantity' => '2 litres',
                'due_at' => now()->subMinute()->toDateTimeString(),
            ])
            ->assertCreated()
            ->json('data.id');

        $reminder = $this->withBypass(fn () => Reminder::withoutGlobalScope('tenant')
            ->where('request_id', $id)
            ->where('status', 'pending')
            ->firstOrFail());

        return [$reminder, $member];
    }

    public function test_the_command_queues_a_job_per_due_reminder(): void
    {
        Queue::fake();

        [$reminder] = $this->dueRequestToken();

        $this->artisan('reminders:dispatch-due')->assertSuccessful();

        Queue::assertPushed(DispatchReminder::class, fn (DispatchReminder $job) => $job->reminderId === $reminder->id);
    }

    public function test_the_job_sends_the_push_and_writes_a_log(): void
    {
        [$reminder, $member] = $this->dueRequestToken();

        (new DispatchReminder($reminder->id))->handle(app(ReminderDispatcher::class));

        $this->withBypass(function () use ($reminder, $member): void {
            $fresh = Reminder::withoutGlobalScope('tenant')->findOrFail($reminder->id);

            $this->assertSame('sent', $fresh->status->value);
            $this->assertNotNull($fresh->dispatched_at);

            $log = NotificationLog::withoutGlobalScope('tenant')
                ->where('reminder_id', $reminder->id)
                ->firstOrFail();

            $this->assertSame('sent', $log->status->value);
            $this->assertSame('push', $log->channel->value);
            $this->assertSame('log', $log->provider);
            $this->assertSame($member->id, $log->user_id);
        });
    }

    public function test_a_closed_request_is_skipped_and_the_reminder_cancelled(): void
    {
        [$reminder] = $this->dueRequestToken();

        // Force the request closed without going through the workflow, leaving a
        // pending reminder behind (the dispatcher must still refuse to send).
        $this->withBypass(fn () => Reminder::withoutGlobalScope('tenant')
            ->where('id', $reminder->id)
            ->update(['status' => 'pending']));

        $this->withBypass(fn () => \App\Models\ServiceRequest::withoutGlobalScope('tenant')
            ->where('id', $reminder->request_id)
            ->update(['status' => 'completed', 'completed_at' => now()]));

        (new DispatchReminder($reminder->id))->handle(app(ReminderDispatcher::class));

        $this->withBypass(function () use ($reminder): void {
            $fresh = Reminder::withoutGlobalScope('tenant')->findOrFail($reminder->id);

            $this->assertSame('cancelled', $fresh->status->value);

            $log = NotificationLog::withoutGlobalScope('tenant')
                ->where('reminder_id', $reminder->id)
                ->firstOrFail();

            $this->assertSame('skipped', $log->status->value);
        });
    }

    public function test_quiet_hours_defers_the_reminder_instead_of_sending(): void
    {
        [$reminder] = $this->dueRequestToken();

        $this->withBypass(fn () => Tenant::withoutGlobalScope('tenant')
            ->whereKey($reminder->tenant_id)
            ->update(['settings' => ['quiet_hours' => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']]]));

        (new DispatchReminder($reminder->id))->handle(app(ReminderDispatcher::class));

        $this->withBypass(function () use ($reminder): void {
            $fresh = Reminder::withoutGlobalScope('tenant')->findOrFail($reminder->id);

            $this->assertSame('pending', $fresh->status->value);
            $this->assertTrue($fresh->fire_at->isFuture());

            $log = NotificationLog::withoutGlobalScope('tenant')
                ->where('reminder_id', $reminder->id)
                ->firstOrFail();

            $this->assertSame('skipped', $log->status->value);
        });
    }

    public function test_a_provider_failure_marks_the_reminder_failed(): void
    {
        [$reminder] = $this->dueRequestToken();

        $this->app->instance(PushSender::class, new class implements PushSender
        {
            public function send(\App\Models\User $recipient, \App\Notifications\PushMessage $message): array
            {
                throw new RuntimeException('provider exploded');
            }
        });

        (new DispatchReminder($reminder->id))->handle(app(ReminderDispatcher::class));

        $this->withBypass(function () use ($reminder): void {
            $fresh = Reminder::withoutGlobalScope('tenant')->findOrFail($reminder->id);

            $this->assertSame('failed', $fresh->status->value);

            $log = NotificationLog::withoutGlobalScope('tenant')
                ->where('reminder_id', $reminder->id)
                ->firstOrFail();

            $this->assertSame('failed', $log->status->value);
            $this->assertSame('provider exploded', $log->error);
        });
    }
}
