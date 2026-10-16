<?php

namespace App\Reminders;

use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use App\Models\Reminder;
use App\Models\ServiceRequest;

/**
 * Keeps the `reminders` table in step with a request. Called from
 * RequestWorkflow after every mutation, so a request has exactly one owner of
 * its status and one owner of its reminders.
 */
class ReminderScheduler
{
    public function sync(ServiceRequest $request): void
    {
        $this->cancelPending($request);

        if (! $request->status->isOpen()) {
            return;
        }

        $fireAt = $request->snooze_until ?? $request->due_at;

        if ($fireAt === null) {
            return;
        }

        Reminder::create([
            'tenant_id' => $request->tenant_id,
            'request_id' => $request->getKey(),
            'type' => $request->snooze_until !== null ? ReminderType::Snooze : ReminderType::Due,
            'fire_at' => $fireAt,
            'status' => ReminderStatus::Pending,
        ]);
    }

    public function cancelPending(ServiceRequest $request): int
    {
        return Reminder::query()
            ->where('request_id', $request->getKey())
            ->where('status', ReminderStatus::Pending->value)
            ->update([
                'status' => ReminderStatus::Cancelled->value,
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
