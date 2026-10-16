<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The platform inbox view: adds which household reported it, and who triaged it.
 *
 * @mixin \App\Models\FeedbackReport
 */
class PlatformFeedbackResource extends JsonResource
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
            'resolution_note' => $this->resolution_note,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'age_hours' => $this->created_at === null
                ? null
                : (int) $this->created_at->diffInHours(now()),
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant === null ? null : [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'subscription_tier' => $this->tenant->subscription_tier,
                'status' => $this->tenant->status,
            ]),
            'reporter' => $this->whenLoaded('reporter', fn () => $this->reporter === null ? null : [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
                'email' => $this->reporter->email,
            ]),
            'resolver' => $this->whenLoaded('resolver', fn () => $this->resolver === null ? null : [
                'id' => $this->resolver->id,
                'name' => $this->resolver->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
