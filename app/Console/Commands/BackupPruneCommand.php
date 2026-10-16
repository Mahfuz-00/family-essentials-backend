<?php

namespace App\Console\Commands;

use App\Ops\Backups\BackupService;
use Illuminate\Console\Command;

class BackupPruneCommand extends Command
{
    protected $signature = 'backups:prune';

    protected $description = 'Delete backups past the retention window';

    public function handle(BackupService $backups): int
    {
        $reaped = $backups->reapAbandoned();

        if ($reaped > 0) {
            $this->line("marked {$reaped} interrupted run(s) as failed");
        }

        $removed = $backups->prune();

        $this->line("removed {$removed} backup(s) past ".config('backup.retention_days').' days');

        return self::SUCCESS;
    }
}
