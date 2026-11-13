<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Essential
 */
class EssentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'default_store' => $this->default_store,
            'pack_size' => $this->pack_size,
            'cadence_days' => (int) $this->cadence_days,
            'threshold' => (int) $this->threshold,
            'stock_level' => (int) $this->stock_level,
            'unit_price_minor' => (int) ($this->unit_price_minor ?? 0),
            'last_spent_minor' => (int) ($this->last_spent_minor ?? 0),
            'total_spent_minor' => (int) ($this->total_spent_minor ?? 0),
            'projected_restock_minor' => (int) (max(0, (int) $this->threshold - (int) $this->stock_level) * (int) ($this->unit_price_minor ?? 0)),
            'is_active' => (bool) $this->is_active,
            'last_restocked_at' => $this->last_restocked_at?->toISOString(),
            'next_due_at' => $this->nextDueAt()?->toISOString(),
            'days_until_due' => $this->daysUntilDue(),
            'is_due' => $this->isDue(),
            'due_reason' => $this->dueReason(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
