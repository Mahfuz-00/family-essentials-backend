<?php

namespace App\Ops\Backups;

/**
 * What a successful run produced. The checksum is computed from the file on
 * disk after the dump finishes, so verification later compares against what was
 * actually written rather than what we intended to write.
 */
final class BackupResult
{
    public function __construct(
        public readonly string $filename,
        public readonly string $disk,
        public readonly int $sizeBytes,
        public readonly string $checksum,
    ) {}
}
