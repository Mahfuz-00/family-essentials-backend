<?php

namespace App\Http\Controllers\Api\V1\Tenant\Concerns;

use App\Models\TenantUser;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Household authorization helpers.
 *
 * These delegate to TenantUserPolicy, which reads config/roles.php, so the rules
 * live in one place. Prefer calling `$this->authorize('…', TenantUser::class)`
 * directly in new code; these remain for the call sites that read better with a
 * named guard.
 */
trait AuthorizesTenantAdmin
{
    /**
     * Administrator of any rank (tenant_admin or co_admin).
     */
    protected function authorizeTenantAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->can('invite', TenantUser::class) === true,
            403,
            'Only household administrators can perform this action.'
        );
    }

    /**
     * The primary administrator only — role changes and member removal.
     */
    protected function authorizeTenantOwner(Request $request): void
    {
        abort_unless(
            $request->user()?->can('manageRoles', TenantUser::class) === true,
            403,
            'Only the primary tenant administrator can change roles or remove members.'
        );
    }

    /**
     * The caller's own membership. Tenant-explicit rather than relying solely on
     * the global scope, so this stays correct if the scope is ever bypassed.
     */
    protected function membership(Request $request): ?TenantUser
    {
        return TenantUser::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('user_id', $request->user()?->getKey())
            ->first();
    }
}
