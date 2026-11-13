<?php

namespace App\Models;

use App\Enums\GeofenceEventType;
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
    'item_id', 'title', 'quantity', 'items', 'quantity_got', 'notes', 'category', 'priority',
    'store_name', 'place_id', 'assignee_id', 'due_at', 'snooze_until',
    'location_trigger_place_id', 'location_trigger_direction',
    // The ledger a request is charged to, and how much. `recurring_rule_id` and
    // `recurring_for` are deliberately *not* fillable: they are evidence of which
    // cycle produced this row, so only the spawn command writes them.
    'amount_minor', 'ledger_id',
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
            'items' => 'array',
            'priority' => RequestPriority::class,
            'status' => RequestStatus::class,
            'location_trigger_direction' => GeofenceEventType::class,
            'due_at' => 'datetime',
            'snooze_until' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'voice_escalation_attempts' => 'integer',
            'last_voice_attempt_at' => 'datetime',
            'recurring_for' => 'date',
            'amount_minor' => 'integer',
        ];
    }

    /**
     * Get the item list, synthesizing a single-item list from legacy title/quantity if items array is empty.
     *
     * @return array<int, array{name: string, quantity_asked: ?string, quantity_got: ?string, is_picked: bool}>
     */
    public function getItemsListAttribute(): array
    {
        $raw = $this->items;
        if (is_array($raw) && ! empty($raw)) {
            return array_map(function ($entry) {
                return [
                    'name' => (string) ($entry['name'] ?? $entry['title'] ?? ''),
                    'quantity_asked' => isset($entry['quantity_asked']) ? (string) $entry['quantity_asked'] : ($entry['quantity'] ?? null),
                    'quantity_got' => isset($entry['quantity_got']) ? (string) $entry['quantity_got'] : null,
                    'is_picked' => (bool) ($entry['is_picked'] ?? false),
                ];
            }, $raw);
        }

        if (filled($this->title)) {
            return [[
                'name' => (string) $this->title,
                'quantity_asked' => $this->quantity,
                'quantity_got' => $this->quantity_got,
                'is_picked' => $this->status === RequestStatus::Completed,
            ]];
        }

        return [];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * The rule this request was spawned from, if it was.
     */
    public function recurringRule(): BelongsTo
    {
        return $this->belongsTo(RecurringRule::class, 'recurring_rule_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * The place that cues the reminder. Deliberately separate from `place()`:
     * where to buy it and where to remind me are different facts.
     */
    public function triggerPlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'location_trigger_place_id');
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
