<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A household's view of its own report — no internal triage notes, no other
 * household's identity.
 *
 * @mixin \App\Models\FeedbackReport
 */
class FeedbackReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'subject' => $this->subject,
            'body' => $this->body,
            'context' => $this->context,
            // Shown to the household so "resolved" is never unexplained.
            'resolution_note' => $this->resolution_note,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'reporter' => $this->whenLoaded('reporter', fn () => $this->reporter === null ? null : [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
