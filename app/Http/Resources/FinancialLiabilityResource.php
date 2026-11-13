<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FinancialLiability
 */
class FinancialLiabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'lender' => $this->lender,
            'principal_amount_minor' => $this->principal_amount_minor,
            'remaining_balance_minor' => $this->remaining_balance_minor,
            'monthly_payment_minor' => $this->monthly_payment_minor,
            'interest_rate_bps' => $this->interest_rate_bps,
            'interest_rate_percent' => $this->interest_rate_percent,
            'payoff_percent' => $this->payoff_percent,
            'due_day_of_month' => $this->due_day_of_month,
            'start_date' => $this->start_date?->toDateString(),
            'maturity_date' => $this->maturity_date?->toDateString(),
            'currency' => $this->currency,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
