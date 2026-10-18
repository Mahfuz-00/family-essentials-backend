<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\LedgerEntry
 */
class LedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ledger_id' => $this->ledger_id,
            // Integer minor units, always. Never a float, never a currency string
            // the client has to parse — see config/ledger.php.
            'amount_minor' => (int) $this->amount_minor,
            'direction' => $this->direction->value,
            'category' => $this->category,
            'description' => $this->description,
            'occurred_on' => $this->occurred_on?->toDateString(),
            'paid_by' => $this->paid_by,
            'paid_by_name' => $this->whenLoaded('payer', fn () => $this->payer?->name),
            'source' => $this->source->value,
            'request_id' => $this->request_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
