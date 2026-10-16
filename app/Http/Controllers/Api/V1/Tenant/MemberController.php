<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\UpdateMemberRoleRequest;
use App\Http\Resources\TenantMemberResource;
use App\Models\DeviceToken;
use App\Models\ServiceRequest;
use App\Models\TenantUser;
use App\Models\User;
use App\Tenancy\RoleCapabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Household roster and membership administration.
 *
 * Authorization is entirely policy-driven (TenantUserPolicy, reading
 * config/roles.php) so the rules cannot drift from what the dashboard claims.
 */
class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Readable by any active member: the mobile assignee picker needs it.
        $this->authorize('viewAny', TenantUser::class);

        $members = TenantUser::query()->with('user')->get();

        $userIds = $members->pluck('user_id')->all();

        // Real telemetry: paired devices and live workload per member.
        $devices = DeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->orderByDesc('last_used_at')
            ->get()
            ->groupBy('user_id');

        $workload = ServiceRequest::query()
            ->whereNotNull('assignee_id')
            ->whereIn('status', ['pending', 'accepted', 'in_progress'])
            ->selectRaw('assignee_id, count(*) as total')
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id');

        $members->each(function (TenantUser $member) use ($devices, $workload): void {
            $member->setAttribute(
                'devices',
                $devices->get($member->user_id, collect())
                    ->map(fn (DeviceToken $token) => [
                        'platform' => $token->platform,
                        'last_used_at' => $token->last_used_at?->toISOString(),
                    ])
                    ->values()
                    ->all(),
            );

            $member->setAttribute('open_assignments', (int) ($workload[$member->user_id] ?? 0));
        });

        return TenantMemberResource::collection($members)->response();
    }

    public function update(UpdateMemberRoleRequest $request, User $user): JsonResponse
    {
        $this->authorize('manageRoles', TenantUser::class);

        $membership = TenantUser::query()->where('user_id', $user->getKey())->firstOrFail();
        $role = (string) $request->string('role');

        abort_if(
            $user->getKey() === $request->user()->getKey(),
            422,
            'You cannot change your own role.'
        );

        // Defence in depth. While self-changes are refused above, the only way to
        // demote the last administrator would be for that administrator to demote
        // themselves — so this branch is currently unreachable through the API. It
        // stays because the invariant ("a household always has an admin") is what
        // matters, not the path that happens to enforce it today.
        abort_if(
            $membership->canManageMembers()
                && ! RoleCapabilities::allows('manage_roles', $role)
                && $this->tenantAdminCount() <= 1,
            422,
            'A household needs at least one tenant administrator.'
        );

        $previous = $membership->role;
        $membership->update(['role' => $role]);

        app(AuditRecorder::class)->record('member.role_changed', [
            'member' => $user->name,
            'member_id' => $user->getKey(),
            'from' => $previous,
            'to' => $role,
        ], 'warning');

        return (new TenantMemberResource($membership->load('user')))->response();
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('remove', [TenantUser::class, $user]);

        $membership = TenantUser::query()->where('user_id', $user->getKey())->firstOrFail();

        abort_if(
            $membership->canManageMembers() && $this->tenantAdminCount() <= 1,
            422,
            'A household needs at least one tenant administrator.'
        );

        $membership->delete();

        app(AuditRecorder::class)->record('member.removed', [
            'member' => $user->name,
            'member_id' => $user->getKey(),
        ], 'warning');

        return response()->json(['message' => 'Member removed.']);
    }

    private function tenantAdminCount(): int
    {
        return TenantUser::query()->where('role', 'tenant_admin')->count();
    }
}
