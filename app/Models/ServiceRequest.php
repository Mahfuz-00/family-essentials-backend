<?php

namespace App\Models;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The PRD's `requests` entity. Named ServiceRequest to avoid colliding with
 * Illuminate\Http\Request in controller signatures.
 */
#[Fillable([
    'item_id', 'title', 'quantity', 'notes', 'category', 'priority',
    'store_name', 'place_id', 'assignee_id', 'due_at', 'snooze_until',
])]
class ServiceRequest extends Model
{
    use BelongsToTenant;

    protected $table = 'requests';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => RequestPriority::class,
            'status' => RequestStatus::class,
            'due_at' => 'datetime',
            'snooze_until' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'voice_escalation_attempts' => 'integer',
            'last_voice_attempt_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'request_id')->latest('id');
    }

    public function voiceCallLogs(): HasMany
    {
        return $this->hasMany(VoiceCallLog::class, 'request_id')->latest('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'request_id')->latest('id');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
