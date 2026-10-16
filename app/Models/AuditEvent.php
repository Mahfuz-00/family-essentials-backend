<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single entry in the platform security ledger. Not tenant-scoped: rows are
 * intentionally readable across tenants by platform administrators only.
 */
#[Fillable([
    'trace_id',
    'occurred_at',
    'level',
    'action',
    'tenant_id',
    'actor_user_id',
    'actor_email',
    'actor_role',
    'source_ip',
    'user_agent',
    'http_status',
    'payload',
])]
class AuditEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
