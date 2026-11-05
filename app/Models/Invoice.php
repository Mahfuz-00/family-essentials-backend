<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'tenant_id',
        'invoice_number',
        'amount_cents',
        'currency',
        'status',
        'tier',
        'billing_period',
        'period_start',
        'period_end',
        'paid_at',
        'payment_method',
        'last4',
        'line_items',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'paid_at' => 'datetime',
        'line_items' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function generateNumber(): string
    {
        $year = date('Y');
        $random = str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        return "INV-{$year}-{$random}";
    }
}
