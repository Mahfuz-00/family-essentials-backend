<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Proves the scheduler is alive. Without it, "the scheduler is fine" would be an
 * assumption rather than an observation.
 */
class ScheduleHeartbeatCommand extends Command
{
    protected $signature = 'ops:heartbeat';

    protected $description = 'Record that the scheduler ran';

    public function handle(): int
    {
        Cache::put((string) config('ops.heartbeat_key'), now(), now()->addHours(2));

        return self::SUCCESS;
    }
}
