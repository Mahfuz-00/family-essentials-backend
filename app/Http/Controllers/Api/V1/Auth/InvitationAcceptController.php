<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\AcceptInvitationRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;

class InvitationAcceptController extends Controller
{
    public function store(AcceptInvitationRequest $request): JsonResponse
    {
        $user = $request->user();

        $invitation = TenantInvitation::withoutGlobalScope('tenant')
            ->where('token', (string) $request->string('token'))
            ->first();

        abort_if(! $invitation || ! $invitation->isPending(), 422, 'This invitation is invalid or has expired.');

        $matchesEmail = $invitation->email !== null
            && strcasecmp($invitation->email, (string) $user->email) === 0;

        $matchesPhone = $invitation->phone !== null
            && $invitation->phone === $user->phone;

        abort_unless($matchesEmail || $matchesPhone, 403, 'This invitation was issued to a different account.');

        $membership = TenantUser::withoutGlobalScope('tenant')->firstOrCreate(
            ['tenant_id' => $invitation->tenant_id, 'user_id' => $user->getKey()],
            [
                'role' => $invitation->role,
                'status' => 'active',
                'invited_by' => $invitation->invited_by,
                'joined_at' => now(),
            ],
        );

        if (! $membership->wasRecentlyCreated) {
            $membership->update([
                'role' => $invitation->role,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        $invitation->update(['accepted_at' => now()]);

        if ($user->active_tenant_id === null) {
            $user->forceFill(['active_tenant_id' => $invitation->tenant_id])->save();
        }

        $tenant = Tenant::withoutGlobalScope('tenant')->findOrFail($invitation->tenant_id);

        return response()->json([
            'message' => 'Invitation accepted.',
            'tenant' => new TenantResource($tenant),
        ]);
    }
}
