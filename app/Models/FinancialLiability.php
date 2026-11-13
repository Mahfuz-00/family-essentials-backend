<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'created_by_id',
    'name',
    'type',
    'lender',
    'principal_amount_minor',
    'remaining_balance_minor',
    'monthly_payment_minor',
    'interest_rate_bps',
    'due_day_of_month',
    'start_date',
    'maturity_date',
    'currency',
    'status',
    'notes',
])]
class FinancialLiability extends Model
{
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'principal_amount_minor' => 'integer',
            'remaining_balance_minor' => 'integer',
            'monthly_payment_minor' => 'integer',
            'interest_rate_bps' => 'integer',
            'due_day_of_month' => 'integer',
            'start_date' => 'date',
            'maturity_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getInterestRatePercentAttribute(): ?float
    {
        return $this->interest_rate_bps !== null ? round($this->interest_rate_bps / 100, 2) : null;
    }

    public function getPayoffPercentAttribute(): float
    {
        if ($this->principal_amount_minor <= 0) {
            return 0.0;
        }

        $paid = $this->principal_amount_minor - $this->remaining_balance_minor;

        return round(max(0.0, min(100.0, ($paid / $this->principal_amount_minor) * 100)), 1);
    }
}
