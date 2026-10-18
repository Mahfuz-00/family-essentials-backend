<?php

namespace App\Models;

use App\Enums\LedgerRole;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pot of money the household (or some of it) keeps track of.
 *
 * Isolation for this table is per *person*: RLS only reveals ledgers the caller
 * has a membership row for, so `Ledger::query()` is already the list of ledgers
 * the caller belongs to.
 */
#[Fillable(['name', 'owner_id', 'currency'])]
class Ledger extends Model
{
    use BelongsToTenant;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(LedgerMember::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Set by the controller from the caller's own membership row; read by
     * {@see \App\Http\Resources\LedgerResource}.
     */
    public function myRole(): ?LedgerRole
    {
        $role = $this->getAttribute('my_role');

        return $role instanceof LedgerRole ? $role : ($role === null ? null : LedgerRole::tryFrom((string) $role));
    }
}
