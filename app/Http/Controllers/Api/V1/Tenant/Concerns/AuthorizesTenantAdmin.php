<?php

namespace App\Http\Controllers\Api\V1\Tenant\Concerns;

use App\Models\TenantUser;
use Illuminate\Http\Request;

trait AuthorizesTenantAdmin
{
    protected function authorizeTenantAdmin(Request $request): void
    {
        abort_unless(
            $this->membership($request)?->isAdmin() === true,
            403,
            'Only household administrators can perform this action.'
        );
    }

    /**
     * Stricter check for role changes and member removal: primary admin only.
     */
    protected function authorizeTenantOwner(Request $request): void
    {
        abort_unless(
            $this->membership($request)?->canManageMembers() === true,
            403,
            'Only the primary tenant administrator can change roles or remove members.'
        );
    }

    private function membership(Request $request): ?TenantUser
    {
        return TenantUser::query()
            ->where('user_id', $request->user()->getKey())
            ->first();
    }
}
