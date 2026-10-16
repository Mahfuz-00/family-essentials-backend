<?php

namespace App\Models;

use App\Enums\VoiceCallStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'request_id', 'user_id', 'provider', 'provider_call_id', 'idempotency_key',
    'status', 'reason', 'attempts', 'duration_seconds', 'error', 'initiated_at', 'answered_at', 'ended_at',
])]
class VoiceCallLog extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VoiceCallStatus::class,
            'attempts' => 'integer',
            'duration_seconds' => 'integer',
            'initiated_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
