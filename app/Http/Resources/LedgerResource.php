<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Ledger
 */
class LedgerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency' => $this->currency,
            'owner_id' => $this->owner_id,
            'owner_name' => $this->whenLoaded('owner', fn () => $this->owner?->name),
            // The caller's own place in it, and how many people share it. Both
            // are set by the controller: the first from the caller's membership
            // row, the second with a system count.
            'my_role' => $this->myRole()?->value,
            'member_count' => (int) ($this->member_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
