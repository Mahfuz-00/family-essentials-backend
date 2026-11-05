<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CalendarFeedToken extends Model
{
    use HasFactory;

    protected $table = 'calendar_feed_tokens';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'token',
        'scope',
        'last_accessed_at',
    ];

    protected $casts = [
        'last_accessed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forTenant(Tenant $tenant, ?User $user = null): self
    {
        return self::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user?->id,
            ],
            [
                'token' => Str::random(48),
                'scope' => $user ? 'member' : 'household',
            ]
        );
    }
}
