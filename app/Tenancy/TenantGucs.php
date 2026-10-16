<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\DB;

/**
 * Mirrors the {@see TenantContext} into the PostgreSQL session GUCs that the
 * RLS policies read: `app.current_tenant` and `app.bypass_rls`.
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
            'app.bypass_rls',
            $context->isBypassed() ? 'on' : 'off',
        ]);
    }
}
