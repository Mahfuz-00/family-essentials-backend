<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\SwitchTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantMembershipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $memberships = TenantUser::withoutGlobalScope('tenant')
            ->with(['tenant' => fn ($query) => $query->withoutGlobalScope('tenant')])
            ->where('user_id', $user->getKey())
            ->get();

        return response()->json([
            'data' => $memberships->map(fn (TenantUser $membership): array => [
                'tenant' => new TenantResource($membership->tenant),
                'role' => $membership->role,
                'status' => $membership->status,
                'is_active' => (int) $membership->tenant_id === (int) $user->active_tenant_id,
            ])->values(),
        ]);
    }

    public function switch(SwitchTenantRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = (int) $request->integer('tenant_id');

        $membership = TenantUser::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->first();

        abort_unless($membership, 403, 'You are not an active member of this tenant.');

        $user->forceFill(['active_tenant_id' => $tenantId])->save();

        return response()->json([
            'message' => 'Active tenant updated.',
            'active_tenant_id' => $tenantId,
        ]);
    }
}
