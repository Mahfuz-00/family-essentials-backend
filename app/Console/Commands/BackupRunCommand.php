<?php

namespace App\Console\Commands;

use App\Ops\Backups\BackupService;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'backups:run {--type=scheduled : manual, scheduled or pre_restore}';

    protected $description = 'Take a database backup and register it';

    public function handle(BackupService $backups): int
    {
        $backup = $backups->run((string) $this->option('type'));

        if ($backup->status === 'skipped') {
            $this->warn('skipped: '.$backup->error);

            return self::SUCCESS;
        }

        if ($backup->status !== 'completed') {
            $this->error('failed: '.$backup->error);

            // A failed backup is an operational failure, so the scheduler records it.
            return self::FAILURE;
        }

        $this->line(sprintf(
            'completed %s (%s MB) in %ss',
            $backup->filename,
            number_format(($backup->size_bytes ?? 0) / 1048576, 2),
            $backup->duration_seconds,
        ));

        // Prove the file is intact while it is fresh, not on the day it is needed.
        $verified = $backups->verify($backup);

        $this->line($verified->verify_error === null
            ? 'verified: checksum matches'
            : 'verify failed: '.$verified->verify_error);

        return self::SUCCESS;
    }
}
