<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Audit\AuditRecorder;
use App\Enums\LedgerRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledgers\StoreLedgerMemberRequest;
use App\Http\Resources\LedgerMemberResource;
use App\Ledgers\LedgerMembership;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Who may see a ledger, and how much of it.
 *
 * `{ledger}` is resolved through route-model binding, which runs *after* the
 * tenant and person GUCs are set — so a ledger the caller is not a member of
 * does not exist for them and the request is a 404 before any policy question
 * is asked.
 */
class LedgerMemberController extends Controller
{
    public function __construct(
        private readonly LedgerMembership $members,
        private readonly AuditRecorder $audit,
    ) {}

    public function store(StoreLedgerMemberRequest $request, Ledger $ledger): JsonResponse
    {
        $this->authorize('manageMembers', $ledger);

        $user = User::query()->findOrFail($request->integer('user_id'));
        $role = LedgerRole::from($request->string('role')->toString());

        $member = $this->members->put($ledger, $user, $role);

        $this->audit->record('ledger.member_added', [
            'ledger_id' => (int) $ledger->getKey(),
            'member_id' => (int) $user->getKey(),
            'role' => $role->value,
        ]);

        return (new LedgerMemberResource($member->load('user')))->response()->setStatusCode(201);
    }

    public function destroy(Ledger $ledger, User $user): JsonResponse
    {
        // The owner cannot remove themselves: a ledger with nobody able to
        // manage it is a ledger nobody can fix.
        $this->authorize('removeMember', [$ledger, $user]);

        abort_if(
            $this->members->revoke($ledger, $user) === 0,
            404,
            'That member is not part of this ledger.',
        );

        $this->audit->record('ledger.member_removed', [
            'ledger_id' => (int) $ledger->getKey(),
            'member_id' => (int) $user->getKey(),
        ], 'warning');

        return response()->json(['message' => 'Member removed from the ledger.']);
    }
}
