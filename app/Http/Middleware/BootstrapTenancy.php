<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantGucs;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For identity-level endpoints (list my households, switch household, accept an
 * invitation) that must read membership before a tenant has been chosen. Runs
 * with the RLS bypass enabled; controllers scope queries to the auth user.
 */
class BootstrapTenancy
{
    public function handle(Request $request, Closure $next): Response
    {
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
