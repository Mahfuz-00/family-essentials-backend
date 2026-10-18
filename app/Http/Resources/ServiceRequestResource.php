<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
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
            'status' => $this->status->value,
            'store_name' => $this->store_name,
            'due_at' => $this->due_at?->toISOString(),
            'snooze_until' => $this->snooze_until?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'item_id' => $this->item_id,
            'item' => $this->whenLoaded('item', fn () => new ItemResource($this->item)),
            'place_id' => $this->place_id,
            'place' => $this->whenLoaded('place', fn () => $this->place === null ? null : new PlaceResource($this->place)),
            // The member's own reminder cue, separate from the shop above.
            'location_trigger' => $this->locationTrigger(),
            'requested_by' => $this->whenLoaded('requester', fn () => $this->requester === null ? null : [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'history' => $this->whenLoaded('assignments', fn () => AssignmentResource::collection($this->assignments)),
            'reminders' => $this->whenLoaded('reminders', fn () => ReminderResource::collection($this->reminders)),
            'voice_calls' => $this->whenLoaded('voiceCallLogs', fn () => VoiceCallResource::collection($this->voiceCallLogs)),
            // Set by the recurring command when this request stands for one cycle
            // of a rule, so the client can group cycles without guessing.
            'recurring_rule_id' => $this->recurring_rule_id,
            'recurring_for' => $this->recurring_for?->toDateString(),
            // The ledger this task is charged to, in integer minor units.
            'amount_minor' => $this->amount_minor,
            'ledger_id' => $this->ledger_id,
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
            // Only present when the relation was eager-loaded, so a list endpoint
            // does not pay for a query per row.
            'place_name' => $this->relationLoaded('triggerPlace') ? $this->triggerPlace?->name : null,
        ], static fn ($value): bool => $value !== null);
    }
}
