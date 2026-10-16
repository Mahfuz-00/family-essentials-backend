<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Incident
 */
class IncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'severity' => $this->severity,
            'status' => $this->status,
            'body' => $this->body,
            'started_at' => $this->started_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'duration_minutes' => $this->started_at === null
                ? null
                : (int) $this->started_at->diffInMinutes($this->resolved_at ?? now()),
            'author' => $this->whenLoaded('author', fn () => $this->author === null ? null : [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
        ];
    }
}
