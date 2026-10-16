<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\AcceptInvitationRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Plans\QuotaService;
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

        // Accepting must never modify an existing membership. Updating the role on
        // accept was a privilege-escalation path: invite your own address as a
        // tenant_admin and accept it to overwrite your role.
        $existing = TenantUser::withoutGlobalScope('tenant')
            ->where('tenant_id', $invitation->tenant_id)
            ->where('user_id', $user->getKey())
            ->first();

        abort_if(
            $existing !== null,
            422,
            'You are already a member of this household.'
        );

        // Re-check the seat at the moment it is taken: invitations can sit for
        // days, and the household may have filled up in the meantime. There is no
        // tenant context on this route, so the tenant is passed explicitly.
        app(QuotaService::class)->assertCanAddSeat(
            Tenant::withoutGlobalScope('tenant')->findOrFail($invitation->tenant_id)
        );

        TenantUser::withoutGlobalScope('tenant')->create([
            'tenant_id' => $invitation->tenant_id,
            'user_id' => $user->getKey(),
            'role' => $invitation->role,
            'status' => 'active',
            'invited_by' => $invitation->invited_by,
            'joined_at' => now(),
        ]);

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
