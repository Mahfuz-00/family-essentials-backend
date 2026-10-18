<?php

namespace App\Models;

use App\Enums\LocationKind;
use App\Enums\LocationNotificationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What happened when a member arrived somewhere: what they were told, or why
 * they were told nothing.
 *
 * One row per recorded entry, enforced by the unique `idempotency_key`, so a
 * repeated position report cannot notify twice.
 */
#[Fillable([
    'tenant_id', 'place_id', 'user_id', 'geofence_event_id', 'status', 'reason', 'kind',
    'task_count', 'task_ids', 'title', 'body', 'notification_log_id',
    'idempotency_key', 'notified_at',
])]
class LocationNotification extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LocationNotificationStatus::class,
            'kind' => LocationKind::class,
            'task_ids' => 'array',
            'task_count' => 'integer',
            'notified_at' => 'datetime',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function geofenceEvent(): BelongsTo
    {
        return $this->belongsTo(GeofenceEvent::class);
    }

    public function notificationLog(): BelongsTo
    {
        return $this->belongsTo(NotificationLog::class);
    }

    public function isSent(): bool
    {
        return $this->status === LocationNotificationStatus::Sent;
    }
}
