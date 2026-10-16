<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recurring household essential with a restock cadence and a safety stock.
 */
#[Fillable([
    'tenant_id',
    'name',
    'category',
    'default_store',
    'pack_size',
    'cadence_days',
    'threshold',
    'stock_level',
    'last_restocked_at',
    'is_active',
    'created_by',
])]
class Essential extends Model
{
    use BelongsToTenant;

    /**
     * Mirrors the table defaults, so a freshly created model behaves the same
     * before and after a round trip (is_active in particular gates isDue()).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cadence_days' => 14,
        'threshold' => 1,
        'stock_level' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_restocked_at' => 'datetime',
            'is_active' => 'boolean',
            'cadence_days' => 'integer',
            'threshold' => 'integer',
            'stock_level' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Due when inactive is false AND either the safety stock is breached or the
     * cadence has elapsed since the last restock.
     */
    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->stock_level <= $this->threshold) {
            return true;
        }

        if ($this->last_restocked_at === null) {
            return true;
        }

        return $this->nextDueAt()->isPast();
    }

    public function nextDueAt(): ?Carbon
    {
        return $this->last_restocked_at?->copy()->addDays((int) $this->cadence_days);
    }

    public function daysUntilDue(): ?int
    {
        $next = $this->nextDueAt();

        return $next === null ? null : (int) now()->diffInDays($next, false);
    }

    /**
     * "Breached" is the safety-stock arm of the rule; "cadence" is the clock arm.
     */
    public function dueReason(): ?string
    {
        if (! $this->isDue()) {
            return null;
        }

        return $this->stock_level <= $this->threshold ? 'safety_stock' : 'cadence';
    }
}
