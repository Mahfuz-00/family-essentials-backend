<?php

namespace App\Models;

use App\Enums\LedgerEntryDirection;
use App\Enums\LedgerEntrySource;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of money, in integer minor units.
 *
 * A `source=request` row is written once per completed request by the bridge in
 * {@see \App\Ledgers\RequestExpenseRecorder}: the unique index on `request_id`
 * is what makes a retried completion harmless.
 */
#[Fillable([
    'tenant_id', 'ledger_id', 'amount_minor', 'direction', 'category', 'description',
    'occurred_on', 'paid_by', 'source', 'request_id', 'created_by',
])]
class LedgerEntry extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'direction' => LedgerEntryDirection::class,
            'source' => LedgerEntrySource::class,
            'occurred_on' => 'date',
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
