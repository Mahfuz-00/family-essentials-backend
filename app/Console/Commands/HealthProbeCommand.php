<?php

namespace App\Console\Commands;

use App\Models\HealthSample;
use App\Ops\HealthReport;
use Illuminate\Console\Command;

/**
 * Samples the system so uptime is measured from observations over time rather
 * than asserted. Also prunes old samples.
 */
class HealthProbeCommand extends Command
{
    protected $signature = 'ops:health-probe {--prune : Only prune old samples}';

    protected $description = 'Record a system health sample and prune expired ones';

    public function handle(HealthReport $report): int
    {
        if (! $this->option('prune')) {
            $result = $report->run();

            HealthSample::create([
                'checked_at' => now(),
                'status' => $result['status'],
                'checks' => $result['checks'],
            ]);

            $this->line('health: '.$result['status']);
        }

        $cutoff = now()->subDays((int) config('ops.sample_retention_days'));
        $pruned = HealthSample::query()->where('checked_at', '<', $cutoff)->delete();

        if ($pruned > 0) {
            $this->line("pruned {$pruned} sample(s) older than {$cutoff->toDateString()}");
        }

        return self::SUCCESS;
    }
}
