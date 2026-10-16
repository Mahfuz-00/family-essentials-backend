<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AuditEvent
 */
class AuditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trace_id' => $this->trace_id,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'level' => $this->level,
            'action' => $this->action,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->whenLoaded('tenant', fn () => $this->tenant?->name),
            'actor_user_id' => $this->actor_user_id,
            'actor_email' => $this->actor_email,
            'actor_role' => $this->actor_role,
            'source_ip' => $this->source_ip,
            'user_agent' => $this->user_agent,
            'http_status' => $this->http_status,
            'payload' => $this->payload,
        ];
    }
}
