<?php

namespace App\Ops\Backups;

/**
 * A dump tool. Implementations must either produce a genuinely restorable file
 * or refuse — never write a placeholder.
 */
interface BackupRunner
{
    /**
     * The identifier recorded on the registry row.
     */
    public function driver(): string;

    /**
     * Whether this driver can produce a restorable dump right now.
     */
    public function isAvailable(): bool;

    /**
     * What an operator must do to make it available, in plain language.
     */
    public function requirement(): string;

    /**
     * Produce the dump and return its measured facts.
     *
     * @throws \RuntimeException when the dump fails
     */
    public function run(string $filename, string $disk): BackupResult;
}
