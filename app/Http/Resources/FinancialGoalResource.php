<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FinancialGoal
 */
class FinancialGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'target_amount_minor' => $this->target_amount_minor,
            'current_amount_minor' => $this->current_amount_minor,
            'progress_percent' => $this->progress_percent,
            'currency' => $this->currency,
            'target_date' => $this->target_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
