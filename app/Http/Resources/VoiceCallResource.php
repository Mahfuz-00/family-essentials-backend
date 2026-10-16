<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoiceCallResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'provider' => $this->provider,
            'provider_call_id' => $this->provider_call_id,
            'attempts' => $this->attempts,
            'duration_seconds' => $this->duration_seconds,
            'error' => $this->error,
            'initiated_at' => $this->initiated_at?->toISOString(),
            'answered_at' => $this->answered_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'request' => $this->whenLoaded('request', fn () => $this->request === null ? null : [
                'id' => $this->request->id,
                'title' => $this->request->title,
            ]),
        ];
    }
}
