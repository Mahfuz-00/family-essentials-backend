<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_meters' => $this->radius_meters,
            'category' => $this->category,
            // The notification-facing kind, derived from category. Sent as a
            // label as well so a client never re-implements the mapping.
            'kind' => $this->kind()->value,
            'kind_label' => $this->kind()->label(),
            'active_requests_count' => $this->whenCounted('requests'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
