<?php

namespace App\Http\Middleware;

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
        abort_if(
            app(TenantContext::class)->id() === null,
            403,
            'No active tenant for this account.'
        );

        return $next($request);
    }
}
