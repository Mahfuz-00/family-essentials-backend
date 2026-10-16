<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'note' => $this->note,
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => [
                'id' => $this->assignedTo?->id,
                'name' => $this->assignedTo?->name,
            ]),
            'assigned_by' => $this->whenLoaded('assignedBy', fn () => $this->assignedBy === null ? null : [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
