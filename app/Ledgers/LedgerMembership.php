<?php

namespace App\Ledgers;

use App\Enums\LedgerRole;
use App\Models\Ledger;
use App\Models\LedgerMember;
use App\Models\User;
use App\Tenancy\Tenancy;

/**
 * Membership of a ledger: who is in it, and how much of it they see.
 *
 * There is exactly one reason any of this reaches for the RLS bypass, and it is
 * worth stating plainly. `ledger_members` is the table the other ledger policies
 * read, so its own policy must not back-reference `ledgers` — PostgreSQL rejects
 * a self-referential policy as infinite recursion. It therefore only shows a
 * person their own row, which means:
 *
 *   - reading somebody else's membership (the roster count, and the row the
 *     owner is about to write) is a system read, and
 *   - granting or revoking membership is a system write.
 *
 * Both are scoped to a ledger the caller has already reached through their own
 * membership, and the API policy (`LedgerPolicy`) decides whether the caller was
 * allowed to ask in the first place. Everything a member reads about themselves
 * — their own role — goes through the ordinary policy.
 */
class LedgerMembership
{
    public function roleFor(Ledger $ledger, ?User $user): ?LedgerRole
    {
        if ($user === null) {
            return null;
        }

        return LedgerMember::query()
            ->where('ledger_id', $ledger->getKey())
            ->where('user_id', $user->getKey())
            ->first()
            ?->role;
    }

    /**
     * ledger id => the caller's role, for the index endpoint.
     *
     * @return array<int, LedgerRole>
     */
    public function rolesFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return LedgerMember::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->mapWithKeys(fn (LedgerMember $member): array => [
                (int) $member->ledger_id => $member->role,
            ])
            ->all();
    }

    /**
     * How many people are in each of these ledgers (system read, see the class
     * docblock — a member cannot count rows that are hidden from them).
     *
     * @param  array<int, int>  $ledgerIds
     * @return array<int, int>
     */
    public function memberCounts(array $ledgerIds): array
    {
        if ($ledgerIds === []) {
            return [];
        }

        return Tenancy::bypass(fn (): array => LedgerMember::query()
            ->whereIn('ledger_id', $ledgerIds)
            ->selectRaw('ledger_id, count(*) as total')
            ->groupBy('ledger_id')
            ->pluck('total', 'ledger_id')
            ->map(fn ($total): int => (int) $total)
            ->all());
    }

    /**
     * Add somebody to a ledger, or change the role of somebody already in it.
     *
     * Idempotent by natural key: asking twice is a role change, not an error.
     */
    public function put(Ledger $ledger, User $member, LedgerRole $role): LedgerMember
    {
        return Tenancy::bypass(fn (): LedgerMember => LedgerMember::query()->updateOrCreate(
            ['ledger_id' => $ledger->getKey(), 'user_id' => $member->getKey()],
            ['tenant_id' => $ledger->tenant_id, 'role' => $role],
        ));
    }

    /**
     * Revoke somebody's access. Returns how many rows went: 0 means they were
     * never a member, which the caller reports as a 404.
     */
    public function revoke(Ledger $ledger, User $member): int
    {
        return (int) Tenancy::bypass(fn (): int => LedgerMember::query()
            ->where('ledger_id', $ledger->getKey())
            ->where('user_id', $member->getKey())
            ->delete());
    }
}
