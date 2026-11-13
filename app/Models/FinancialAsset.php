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
    'institution',
    'balance_minor',
    'contribution_monthly_minor',
    'return_rate_bps',
    'currency',
    'notes',
])]
class FinancialAsset extends Model
{
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'balance_minor' => 'integer',
            'contribution_monthly_minor' => 'integer',
            'return_rate_bps' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getReturnRatePercentAttribute(): ?float
    {
        return $this->return_rate_bps !== null ? round($this->return_rate_bps / 100, 2) : null;
    }
}
