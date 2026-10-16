<?php

namespace App\Http\Middleware;

use App\Audit\AuditRecorder;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantGucs;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to Platform Administrators and enables the tenant-wide RLS
 * bypass for the duration of the request (cross-tenant listing / management).
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isPlatformAdmin() !== true) {
            app(AuditRecorder::class)->record('auth.platform_access_denied', [
                'path' => $request->path(),
                'method' => $request->method(),
            ], 'critical', httpStatus: 403);

            abort(403, 'Platform administrator access required.');
        }

        $context = app(TenantContext::class);
        $context->bypass(true);
        TenantGucs::apply($context);

        try {
            return $next($request);
        } finally {
            $context->reset();
            TenantGucs::apply($context);
        }
    }
}
