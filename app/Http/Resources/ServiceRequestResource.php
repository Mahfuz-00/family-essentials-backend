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
        ];
    }
}
