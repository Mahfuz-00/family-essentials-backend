<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Ops\Backups\BackupService;
use Illuminate\Console\Command;

class BackupVerifyCommand extends Command
{
    protected $signature = 'backups:verify {--id= : Verify one backup instead of every unverified one}';

    protected $description = 'Re-checksum backups to prove they are still restorable';

    public function handle(BackupService $backups): int
    {
        $query = Backup::query()->where('status', Backup::COMPLETED);

        if ($this->option('id') !== null) {
            $query->whereKey((int) $this->option('id'));
        } else {
            $query->whereNull('verified_at');
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->line('nothing to verify');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($rows as $backup) {
            $verified = $backups->verify($backup);

            if ($verified->verify_error === null) {
                $this->line("ok       {$verified->filename}");
                continue;
            }

            $failed++;
            $this->error("failed   {$verified->filename}: {$verified->verify_error}");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
