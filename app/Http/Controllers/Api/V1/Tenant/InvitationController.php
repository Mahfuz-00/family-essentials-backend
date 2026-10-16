<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Api\V1\Tenant\Concerns\AuthorizesTenantAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\InviteMemberRequest;
use App\Http\Resources\TenantInvitationResource;
use App\Models\TenantInvitation;
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
