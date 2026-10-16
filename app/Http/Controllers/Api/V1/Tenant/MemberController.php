<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Api\V1\Tenant\Concerns\AuthorizesTenantAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\UpdateMemberRoleRequest;
use App\Http\Resources\TenantMemberResource;
use App\Models\DeviceToken;
use App\Models\ServiceRequest;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    use AuthorizesTenantAdmin;

    public function index(): JsonResponse
    {
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
        $this->authorizeTenantOwner($request);

        $membership = TenantUser::query()->where('user_id', $user->getKey())->firstOrFail();

        $previous = $membership->role;
        $membership->update(['role' => $request->string('role')]);

        app(AuditRecorder::class)->record('member.role_changed', [
            'member' => $user->name,
            'member_id' => $user->getKey(),
            'from' => $previous,
            'to' => (string) $request->string('role'),
        ], 'warning');

        return (new TenantMemberResource($membership->load('user')))->response();
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeTenantOwner($request);

        abort_if(
            $user->getKey() === $request->user()->getKey(),
            422,
            'You cannot remove yourself from the household.'
        );

        $membership = TenantUser::query()->where('user_id', $user->getKey())->firstOrFail();
        $membership->delete();

        app(AuditRecorder::class)->record('member.removed', [
            'member' => $user->name,
            'member_id' => $user->getKey(),
        ], 'warning');

        return response()->json(['message' => 'Member removed.']);
    }
}
