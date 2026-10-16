<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'subscription_tier' => $this->subscription_tier,
            'settings' => $this->settings ?? [],
            'member_count' => $this->whenCounted('members'),
            'trial_ends_at' => $this->trial_ends_at?->toISOString(),
            'suspended_at' => $this->suspended_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
