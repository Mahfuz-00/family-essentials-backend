<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Api\V1\Tenant\Concerns\AuthorizesTenantAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\InviteMemberRequest;
use App\Http\Resources\TenantInvitationResource;
use App\Models\TenantInvitation;
use App\Plans\QuotaService;
use App\Tenancy\RoleCapabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    use AuthorizesTenantAdmin;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeTenantAdmin($request);

        $invitations = TenantInvitation::query()->latest()->get();

        return TenantInvitationResource::collection($invitations)->response();
    }

    public function store(InviteMemberRequest $request): JsonResponse
    {
        $this->authorizeTenantAdmin($request);

        // Granting an admin role is a role-management action, not an invitation.
        // Without this, a co-admin could invite an address (including their own)
        // as a tenant_admin and escalate their own privileges.
        if (RoleCapabilities::isPrivilegedRole((string) $request->string('role'))) {
            $this->authorizeTenantOwner($request);
        }

        // A pending invitation holds a seat. Letting a household invite past its
        // plan and refusing only at the moment the person accepts would be a nasty
        // surprise for them, and would make the cap meaningless.
        app(QuotaService::class)->assertCanAddSeat();

        $invitation = TenantInvitation::create([
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'role' => $request->input('role'),
            'token' => Str::random(64),
            'invited_by' => $request->user()->getKey(),
            'expires_at' => now()->addDays(7),
        ]);

        app(AuditRecorder::class)->record('invitation.created', [
            'email' => $invitation->email,
            'phone' => $invitation->phone,
            'role' => $invitation->role,
        ]);

        return (new TenantInvitationResource($invitation))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, TenantInvitation $invitation): JsonResponse
    {
        $this->authorizeTenantAdmin($request);

        app(AuditRecorder::class)->record('invitation.revoked', [
            'email' => $invitation->email,
            'phone' => $invitation->phone,
        ], 'warning');

        $invitation->delete();

        return response()->json(['message' => 'Invitation revoked.']);
    }
}
