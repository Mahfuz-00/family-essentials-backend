<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A household's bug report, feedback or feature request.
 *
 * Tenant-scoped: a household sees only its own reports, and only the platform
 * admin can read across households or triage.
 */
#[Fillable([
    'tenant_id', 'user_id', 'type', 'priority', 'status', 'subject', 'body',
    'context', 'resolution_note', 'resolved_at', 'resolved_by',
])]
class FeedbackReport extends Model
{
    use BelongsToTenant;

    public const TYPES = ['bug', 'feedback', 'feature_request'];

    public const PRIORITIES = ['low', 'normal', 'high', 'critical'];

    public const STATUSES = ['new', 'triaged', 'in_progress', 'resolved', 'wont_fix'];

    /**
     * Statuses that mean the report is still somebody's problem.
     */
    public const OPEN_STATUSES = ['new', 'triaged', 'in_progress'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * @param  Builder<FeedbackReport>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::OPEN_STATUSES);
    }
}
