<?php

namespace App\Models;

use App\Enums\LedgerRole;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's place in one ledger.
 *
 * The table is the chart of who may see what, so its own RLS policy is the
 * narrowest in the schema: a person may read their own membership row and no
 * other. Anything that writes somebody else's row (adding a member, removing
 * one) therefore runs under the audited bypass, with the API policy deciding
 * whether the caller was allowed to ask — see App\Ledgers\LedgerMembership.
 */
#[Fillable(['tenant_id', 'ledger_id', 'user_id', 'role'])]
class LedgerMember extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => LedgerRole::class,
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
