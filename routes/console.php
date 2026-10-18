<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:dispatch-due')->everyMinute()->withoutOverlapping();

Schedule::command('voice:escalate-overdue')->everyFiveMinutes()->withoutOverlapping();

// Recurring chores: one fresh request per rule per day, early enough that the
// board is populated before the household wakes up.
Schedule::command('essentials:spawn-recurring')->dailyAt('00:05')->withoutOverlapping();

// Retention: reap the tables that only ever grow (config/retention.php). Daily
// and well clear of the backup window, which wants the disk and the database to
// itself.
Schedule::command('essentials:prune-history')->dailyAt('03:15')->withoutOverlapping();

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
