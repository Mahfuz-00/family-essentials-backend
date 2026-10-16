<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operator-written incident record.
 *
 * Deliberately authored by a human, not auto-detected — the console says so.
 */
#[Fillable(['title', 'severity', 'status', 'body', 'started_at', 'resolved_at', 'created_by'])]
class Incident extends Model
{
    public const SEVERITIES = ['minor', 'major', 'critical'];

    public const STATUSES = ['investigating', 'identified', 'monitoring', 'resolved'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status !== 'resolved';
    }
}
