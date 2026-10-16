<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->status->value,
            'provider' => $this->provider,
            'error' => $this->error,
            'sent_at' => $this->sent_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'request' => $this->whenLoaded('request', fn () => $this->request === null ? null : [
                'id' => $this->request->id,
                'title' => $this->request->title,
            ]),
        ];
    }
}
