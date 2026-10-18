<?php

namespace App\Http\Resources;

use App\Enums\AlertStyle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified' => ! is_null($this->email_verified_at),
            'phone_verified' => ! is_null($this->phone_verified_at),
            'is_platform_admin' => (bool) $this->is_platform_admin,
            'voice_opt_in' => (bool) $this->voice_opt_in,
            'location_notifications_opt_in' => (bool) $this->location_notifications_opt_in,
            'alert_style' => AlertStyle::fromUser($this->alert_style)->value,
            'active_tenant_id' => $this->active_tenant_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
