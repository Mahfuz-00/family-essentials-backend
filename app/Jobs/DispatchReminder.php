<?php

namespace App\Jobs;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Reminders\ReminderDispatcher;
use App\Tenancy\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $reminderId) {}

    /**
     * Runs in a queue worker with no HTTP tenant context, so it resolves the
     * reminder under the audited bypass and then scopes the work to its tenant.
     */
    public function handle(ReminderDispatcher $dispatcher): void
    {
        $reminder = Tenancy::bypass(
            fn () => Reminder::withoutGlobalScope('tenant')->find($this->reminderId)
        );

        if ($reminder === null || $reminder->status !== ReminderStatus::Pending) {
            return;
        }

        Tenancy::forTenant((int) $reminder->tenant_id, function () use ($reminder, $dispatcher): void {
            $fresh = Reminder::query()->find($reminder->getKey());

            if ($fresh !== null && $fresh->status === ReminderStatus::Pending) {
                $dispatcher->dispatch($fresh);
            }
        });
    }
}
