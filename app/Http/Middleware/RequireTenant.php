<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures a tenant context was resolved (see IdentifyTenant). Applied to the
 * tenant workspace routes.
 */
class RequireTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = app(TenantContext::class)->id();

        abort_if(
            $tenantId === null,
            403,
            'No active tenant for this account.'
        );

        $tenant = Tenant::withoutGlobalScope('tenant')->find($tenantId);

        if ($tenant && in_array($tenant->status, ['cancelled', 'suspended'], true)) {
            $path = $request->path();
            // Allow billing routes, platform admin, and logout/tenant info
            $isAllowed = str_contains($path, 'billing') ||
                         str_contains($path, 'tenant') ||
                         str_contains($path, 'auth/logout') ||
                         str_contains($path, 'refund') ||
                         str_contains($path, 'quotas');

            if (! $isAllowed) {
                return response()->json([
                    'message' => 'Please subscribe to a plan to continue using FairShare.',
                    'code' => 'subscription_required',
                    'status' => $tenant->status,
                ], 402);
            }
        }

        return $next($request);
    }
}
