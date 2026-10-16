<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:dispatch-due')->everyMinute()->withoutOverlapping();

Schedule::command('voice:escalate-overdue')->everyFiveMinutes()->withoutOverlapping();

// Operations: prove the scheduler is alive, and sample the system so uptime is
// measured from observations rather than asserted.
Schedule::command('ops:heartbeat')->everyMinute()->withoutOverlapping();

Schedule::command('ops:health-probe')
    ->cron('*/'.(int) config('ops.sample_interval_minutes').' * * * *')
    ->withoutOverlapping();
