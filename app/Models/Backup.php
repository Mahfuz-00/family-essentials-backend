<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A backup artefact and its verification state.
 *
 * `skipped` is a deliberate status: when no dump tool is installed, the run is
 * recorded as skipped with the reason rather than as a success. A registry that
 * counts a skipped run as a backup is worse than an empty one.
 */
#[Fillable([
    'filename', 'disk', 'driver', 'type', 'status', 'size_bytes', 'checksum',
    'started_at', 'finished_at', 'duration_seconds', 'error',
    'verified_at', 'verify_error', 'created_by',
])]
class Backup extends Model
{
    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'verified_at' => 'datetime',
            'size_bytes' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRestorable(): bool
    {
        return $this->status === self::COMPLETED
            && $this->checksum !== null
            && $this->verified_at !== null
            && $this->verify_error === null;
    }

    /**
     * True when the newest verification attempt disagreed with the checksum
     * recorded at creation.
     */
    public function hasDrifted(): bool
    {
        return $this->verify_error !== null;
    }
}
