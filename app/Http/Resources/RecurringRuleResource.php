<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\RecurringRule
 */
class RecurringRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'quantity' => $this->quantity,
            'notes' => $this->notes,
            'category' => $this->category,
            'priority' => $this->priority->value,
            'cadence' => $this->cadence->value,
            'weekday' => $this->weekday,
            'day_of_month' => $this->day_of_month,
            'next_run_on' => $this->next_run_on?->toDateString(),
            'last_run_on' => $this->last_run_on?->toDateString(),
            'active' => (bool) $this->active,
            'assignee_id' => $this->assignee_id,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'place_id' => $this->place_id,
            'place_name' => $this->relationLoaded('place') ? $this->place?->name : null,
            // The member's own reminder cue, exactly as the request carries it.
            'location_trigger' => $this->locationTrigger(),
            'amount_minor' => $this->amount_minor,
            'ledger_id' => $this->ledger_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function locationTrigger(): ?array
    {
        if ($this->location_trigger_place_id === null) {
            return null;
        }

        return array_filter([
            'place_id' => $this->location_trigger_place_id,
            'direction' => $this->location_trigger_direction?->value,
            'place_name' => $this->relationLoaded('triggerPlace') ? $this->triggerPlace?->name : null,
        ], static fn ($value): bool => $value !== null);
    }
}
