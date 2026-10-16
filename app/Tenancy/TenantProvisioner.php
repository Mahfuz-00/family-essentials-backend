<?php

namespace App\Tenancy;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates tenants (and their first admin) as a system operation. Runs with the
 * RLS bypass enabled because provisioning happens before a tenant context exists.
 */
class TenantProvisioner
{
    public function provision(
        string $name,
        ?User $owner = null,
        string $tier = 'pilot',
        string $status = 'trial',
    ): Tenant {
        $context = app(TenantContext::class);
        $previous = $context->isBypassed();

        $context->bypass(true);
        TenantGucs::apply($context);

        try {
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'status' => $status,
                'subscription_tier' => $tier,
            ]);

            if ($owner !== null) {
                TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $owner->id,
                    'role' => 'tenant_admin',
                    'status' => 'active',
                    'joined_at' => now(),
                ]);
            }

            return $tenant;
        } finally {
            $context->bypass($previous);
            TenantGucs::apply($context);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'household';

        return $base.'-'.Str::lower(Str::random(5));
    }

    public function addMember(Tenant $tenant, User $user, string $role = 'member'): TenantUser
    {
        $context = app(TenantContext::class);
        $previous = $context->isBypassed();

        $context->bypass(true);
        TenantGucs::apply($context);

        try {
            return TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        } finally {
            $context->bypass($previous);
            TenantGucs::apply($context);
        }
    }
}
