<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\DB;

/**
 * Mirrors the {@see TenantContext} into the PostgreSQL session GUCs that the
 * RLS policies read: `app.current_tenant`, `app.current_user` and
 * `app.bypass_rls`.
 *
 * `app.current_user` is what makes the ledger tables readable per person rather
 * than per household. It is set in the same places as the tenant GUC so the two
 * can never disagree, and it is cleared the same way — an unset GUC matches no
 * member row, so the ledger policies return zero rows.
 */
class TenantGucs
{
    public static function apply(TenantContext $context): void
    {
        DB::statement('select set_config(?, ?, false)', [
            'app.current_tenant',
            $context->id() === null ? '' : (string) $context->id(),
        ]);

        DB::statement('select set_config(?, ?, false)', [
            'app.current_user',
            $context->userId() === null ? '' : (string) $context->userId(),
        ]);

        DB::statement('select set_config(?, ?, false)', [
            'app.bypass_rls',
            $context->isBypassed() ? 'on' : 'off',
        ]);
    }
}
