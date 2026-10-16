<?php

namespace App\Console\Commands;

use App\Enums\ReminderStatus;
use App\Jobs\DispatchReminder;
use App\Models\Reminder;
use App\Tenancy\Tenancy;
use Illuminate\Console\Command;

class DispatchDueReminders extends Command
{
    protected $signature = 'reminders:dispatch-due {--limit=500}';

    protected $description = 'Queue delivery for reminders whose fire time has passed.';

    public function handle(): int
    {
        $ids = Tenancy::bypass(fn () => Reminder::withoutGlobalScope('tenant')
            ->where('status', ReminderStatus::Pending->value)
            ->where('fire_at', '<=', now())
            ->orderBy('fire_at')
            ->limit((int) $this->option('limit'))
            ->pluck('id')
            ->all());

        foreach ($ids as $id) {
            DispatchReminder::dispatch((int) $id);
        }

        $this->info(sprintf('Queued %d reminder(s).', count($ids)));

        return self::SUCCESS;
    }
}
