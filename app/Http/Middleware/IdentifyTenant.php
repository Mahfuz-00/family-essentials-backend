<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantGucs;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for the request and mirrors it into the PostgreSQL
 * RLS session GUCs.
 *
 * Runs in the `api` group (before SubstituteBindings) so route-model binding on
 * tenant-scoped models is already constrained by the tenant context. It never
 * aborts: routes that require a tenant use the `RequireTenant` middleware. The
 * authenticated user is read straight from the Sanctum guard so resolution does
 * not depend on the `auth` route middleware ordering.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);
        $context->reset();

        // The acting person, alongside the household: the ledger tables are
        // isolated per member, so both GUCs must be in place before route-model
        // binding runs.
        $userId = Auth::guard('sanctum')->user()?->getKey();

        try {
            $tenantId = $this->resolveTenantId($request);

            $context->reset();
            $context->set($tenantId);
            $context->setUser($userId);
            TenantGucs::apply($context);

            return $next($request);
        } finally {
            $context->reset();
            TenantGucs::apply($context);
        }
    }

    private function resolveTenantId(Request $request): ?int
    {
        $user = Auth::guard('sanctum')->user();

        if ($user === null) {
            return null;
        }

        // Resolution queries need the RLS bypass because no tenant context
        // exists yet. It is cleared again before this method returns.
        $context = app(TenantContext::class);
        $context->bypass(true);
        TenantGucs::apply($context);

        try {
            if ($user->isPlatformAdmin() && $request->headers->has('X-Tenant-Id')) {
                $tenantId = (int) $request->header('X-Tenant-Id');

                return Tenant::withoutGlobalScope('tenant')->whereKey($tenantId)->exists()
                    ? $tenantId
                    : null;
            }

            $tenantId = $user->active_tenant_id;

            if ($tenantId === null) {
                return null;
            }

            $isMember = TenantUser::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->getKey())
                ->where('status', 'active')
                ->exists();

            return $isMember ? (int) $tenantId : null;
        } finally {
            $context->bypass(false);
            TenantGucs::apply($context);
        }
    }
}
