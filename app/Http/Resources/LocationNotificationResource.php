<?php

namespace App\Http\Resources;

use App\Enums\LocationNotificationReason;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An arrival decision, as the member's own device needs it: enough to show the
 * notification, and to explain a silence.
 *
 * @mixin \App\Models\LocationNotification
 */
class LocationNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'place_id' => $this->place_id,
            'place_name' => $this->whenLoaded('place', fn () => $this->place?->name),
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'status' => $this->status->value,
            'reason' => $this->reason,
            // The plain-language version of `reason`, so no client has to map it.
            'reason_message' => $this->reason === null
                ? null
                : LocationNotificationReason::tryFrom((string) $this->reason)?->sentence(),
            'title' => $this->title,
            'body' => $this->body,
            'task_count' => $this->task_count,
            // Attached by the notifier from the tasks it already loaded; absent on
            // a refusal, where nothing was resolved.
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks
                ->map(fn (ServiceRequest $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'quantity' => $task->quantity,
                    'status' => $task->status->value,
                ])
                ->values()),
            'notified_at' => $this->notified_at?->toIso8601String(),
        ];
    }
}
