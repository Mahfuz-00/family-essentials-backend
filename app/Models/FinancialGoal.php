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
    'category',
    'target_amount_minor',
    'current_amount_minor',
    'currency',
    'target_date',
    'status',
    'notes',
])]
class FinancialGoal extends Model
{
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'target_amount_minor' => 'integer',
            'current_amount_minor' => 'integer',
            'target_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->target_amount_minor <= 0) {
            return 0.0;
        }

        return round(min(100.0, ($this->current_amount_minor / $this->target_amount_minor) * 100), 1);
    }
}
