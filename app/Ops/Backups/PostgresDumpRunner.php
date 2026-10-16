<?php

namespace App\Ops\Backups;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * A real logical dump via pg_dump, in the custom format (compressed, and
 * restorable with pg_restore --list / -d).
 *
 * It connects using the owner connection, because the runtime role is subject to
 * FORCE ROW LEVEL SECURITY and would export zero rows from every tenant table.
 */
class PostgresDumpRunner implements BackupRunner
{
    private bool $probed = false;

    private bool $available = false;

    private ?string $version = null;

    public function driver(): string
    {
        return 'pg_dump';
    }

    public function isAvailable(): bool
    {
        $this->probe();

        return $this->available;
    }

    public function requirement(): string
    {
        $this->probe();

        return sprintf(
            'Install the PostgreSQL client tools so `%s` is on PATH (a hosted install is usually not), or point BACKUP_PG_DUMP at the binary.',
            (string) config('backup.pg_dump_binary'),
        );
    }

    public function run(string $filename, string $disk): BackupResult
    {
        $connection = (string) config('backup.connection');
        $config = config("database.connections.{$connection}");

        if (! is_array($config)) {
            throw new RuntimeException("The backup connection [{$connection}] is not configured.");
        }

        $directory = (string) config('backup.directory');

        Storage::disk($disk)->makeDirectory($directory);

        $relative = trim($directory, '/').'/'.$filename;
        $target = Storage::disk($disk)->path($relative);

        $process = new Process(
            [
                (string) config('backup.pg_dump_binary'),
                '--host='.(string) ($config['host'] ?? '127.0.0.1'),
                '--port='.(string) ($config['port'] ?? '5432'),
                '--username='.(string) ($config['username'] ?? ''),
                '--format=custom',
                '--no-owner',
                '--no-acl',
                '--file='.$target,
                (string) ($config['database'] ?? ''),
            ],
            null,
            // Never on the command line: it would leak into the process list.
            ['PGPASSWORD' => (string) ($config['password'] ?? '')],
            null,
            (float) config('backup.timeout_seconds'),
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new RuntimeException(
                'The dump exceeded '.config('backup.timeout_seconds').' seconds and was stopped.'
            );
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'pg_dump failed: '.trim($process->getErrorOutput() ?: $process->getOutput())
            );
        }

        if (! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('pg_dump reported success but produced no file.');
        }

        return new BackupResult(
            filename: $relative,
            disk: $disk,
            sizeBytes: (int) filesize($target),
            checksum: (string) hash_file('sha256', $target),
        );
    }

    /**
     * Resolve the binary once. The result is cached because the health report and
     * the console both ask, and shelling out on every question would be wasteful.
     */
    private function probe(): void
    {
        if ($this->probed) {
            return;
        }

        $this->probed = true;

        try {
            $process = new Process([(string) config('backup.pg_dump_binary'), '--version']);
            $process->setTimeout(10);
            $process->run();

            if ($process->isSuccessful()) {
                $this->available = true;
                $this->version = trim($process->getOutput());
            }
        } catch (Throwable) {
            // Missing binary, no permission, or a wedged shell: all mean "not usable".
            $this->available = false;
        }
    }
}
