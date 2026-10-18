<?php

namespace App\Policies;

use App\Enums\LedgerRole;
use App\Ledgers\LedgerMembership;
use App\Models\Ledger;
use App\Models\User;

/**
 * How much of a ledger a member may see.
 *
 * PostgreSQL decides *which* ledgers a person can reach at all (RLS, through
 * their membership rows); this decides what they may do with one. The two are
 * separate on purpose: a `summary` member can reach the ledger, and still may
 * not read a single entry out of it.
 */
class LedgerPolicy
{
    public function __construct(private readonly LedgerMembership $members) {}

    /**
     * Household membership is the gate for opening a ledger of your own; the
     * `tenant` middleware has already established that.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ledger $ledger): bool
    {
        return $this->role($user, $ledger) !== null;
    }

    public function manageMembers(User $user, Ledger $ledger): bool
    {
        return $this->role($user, $ledger)?->managesMembership() === true;
    }

    /**
     * An owner cannot remove themselves: a ledger with nobody able to manage it
     * is a ledger nobody can fix.
     */
    public function removeMember(User $user, Ledger $ledger, User $target): bool
    {
        return $this->manageMembers($user, $ledger) && ! $user->is($target);
    }

    public function viewEntries(User $user, Ledger $ledger): bool
    {
        return $this->role($user, $ledger)?->seesEntries() === true;
    }

    public function createEntry(User $user, Ledger $ledger): bool
    {
        return $this->role($user, $ledger)?->seesEntries() === true;
    }

    public function deleteEntry(User $user, Ledger $ledger): bool
    {
        return $this->role($user, $ledger)?->seesEntries() === true;
    }

    /**
     * Aggregates only. Every member of the ledger may ask for these.
     */
    public function viewSummary(User $user, Ledger $ledger): bool
    {
        return $this->role($user, $ledger) !== null;
    }

    private function role(User $user, Ledger $ledger): ?LedgerRole
    {
        return $this->members->roleFor($ledger, $user);
    }
}
