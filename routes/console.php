<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:dispatch-due')->everyMinute()->withoutOverlapping();

Schedule::command('voice:escalate-overdue')->everyFiveMinutes()->withoutOverlapping();

// Recurring chores: one fresh request per rule per day, early enough that the
// board is populated before the household wakes up.
Schedule::command('essentials:spawn-recurring')->dailyAt('00:05')->withoutOverlapping();

// Operations: prove the scheduler is alive, and sample the system so uptime is
// measured from observations rather than asserted.
Schedule::command('ops:heartbeat')->everyMinute()->withoutOverlapping();
Schedule::command('ops:health-probe')
    ->cron('*/'.(int) config('ops.sample_interval_minutes').' * * * *')
    ->withoutOverlapping();

// Backups: take one nightly, prove it is intact while it is fresh, and prune the
// ones past retention. The dump is verified on the day it is written, not on the
// day it is needed.
Schedule::command('backups:run --type=scheduled')
    ->dailyAt((string) config('backup.daily_at'))
    ->withoutOverlapping();

Schedule::command('backups:verify')->dailyAt('05:00')->withoutOverlapping();

Schedule::command('backups:prune')->dailyAt('04:00')->withoutOverlapping();
