<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Tenancy\RoleCapabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Roles & Permissions module.
 *
 * The capability matrix is served from config/roles.php — the same declaration
 * TenantUserPolicy enforces — instead of the hardcoded table the dashboard used
 * to carry. That is what makes the screen a real module: it cannot claim an
 * ability the API does not honour.
 */
class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TenantUser::class);

        $counts = TenantUser::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $canManage = $request->user()->can('manageRoles', TenantUser::class);

        $roles = collect(RoleCapabilities::roles())
            ->map(fn (array $meta, string $key): array => [
                'key' => $key,
                'label' => $meta['label'],
                'detail' => $meta['detail'],
                'member_count' => (int) ($counts[$key] ?? 0),
                // A co-admin may invite members but not admins, so the UI can
                // offer only what the caller can actually grant.
                'can_be_granted' => $canManage || ! RoleCapabilities::isPrivilegedRole($key),
            ])
            ->values();

        return response()->json([
            'can_manage_roles' => $canManage,
            'roles' => $roles,
            'capabilities' => RoleCapabilities::matrix(),
        ]);
    }
}
