<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TenantUser
 */
class TenantMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->user?->phone,
            'role' => $this->role,
            'status' => $this->status,
            'joined_at' => $this->joined_at?->toISOString(),
            // Populated by the controller from real device tokens / open requests.
            'devices' => $this->when(isset($this->devices), fn () => $this->devices ?? []),
            'open_assignments' => $this->when(isset($this->open_assignments), fn () => $this->open_assignments),
        ];
    }
}
