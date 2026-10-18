<?php

namespace App\Enums;

/**
 * A member's role *in one ledger*, which is a different axis from their
 * household role: the same member can own the grocery ledger and only read the
 * summary of the household one.
 */
enum LedgerRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Summary = 'summary';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Summary => 'Summary only',
        };
    }

    /**
     * May see and change individual entries. A `summary` member may not — that
     * restraint is the whole point of the role.
     */
    public function seesEntries(): bool
    {
        return $this !== self::Summary;
    }

    public function managesMembership(): bool
    {
        return $this === self::Owner;
    }
}
