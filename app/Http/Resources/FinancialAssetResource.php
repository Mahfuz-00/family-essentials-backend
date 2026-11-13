<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FinancialAsset
 */
class FinancialAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'institution' => $this->institution,
            'balance_minor' => $this->balance_minor,
            'contribution_monthly_minor' => $this->contribution_monthly_minor,
            'return_rate_bps' => $this->return_rate_bps,
            'return_rate_percent' => $this->return_rate_percent,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
