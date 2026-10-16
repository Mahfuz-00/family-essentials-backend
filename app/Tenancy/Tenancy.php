<?php

namespace App\Tenancy;

/**
 * Helpers for running code outside an HTTP request (queue workers, scheduled
 * commands) where no tenant context has been established yet.
 *
 * The PostgreSQL RLS policies default-deny without a context, so background
 * work must either enable the audited bypass (cross-tenant scans) or scope
 * itself to a single tenant explicitly.
 */
class Tenancy
{
    public static function bypass(callable $callback, bool $bypass = true): mixed
    {
        $context = app(TenantContext::class);
        $previous = $context->isBypassed();

        $context->bypass($bypass);
        TenantGucs::apply($context);

        try {
            return $callback();
        } finally {
            $context->bypass($previous);
            TenantGucs::apply($context);
        }
    }

    public static function forTenant(?int $tenantId, callable $callback): mixed
    {
        $context = app(TenantContext::class);
        $previousId = $context->id();
        $previousBypass = $context->isBypassed();

        $context->bypass(false);
        $context->set($tenantId);
        TenantGucs::apply($context);

        try {
            return $callback();
        } finally {
            $context->reset();
            $context->set($previousId);
            $context->bypass($previousBypass);
            TenantGucs::apply($context);
        }
    }
}
