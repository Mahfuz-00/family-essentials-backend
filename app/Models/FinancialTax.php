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
    'tax_year',
    'amount_minor',
    'due_date',
    'status',
    'paid_at',
    'currency',
    'notes',
])]
class FinancialTax extends Model
{
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'amount_minor' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
