<?php

namespace App\Http\Controllers\Api\V1\Ledger;

use App\Audit\AuditRecorder;
use App\Enums\LedgerRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledgers\StoreLedgerRequest;
use App\Http\Resources\LedgerResource;
use App\Ledgers\LedgerMembership;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The household ledger.
 *
 * There is deliberately no "show one ledger" endpoint: the only way to name a
 * ledger is as the subject of something else (its entries, its summary, its
 * members), and every one of those paths resolves the ledger through the policy
 * so that a straight id from another household is a 404 rather than a hint that
 * something exists.
 */
class LedgerController extends Controller
{
    public function __construct(
        private readonly LedgerMembership $members,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * The ledgers the caller belongs to. RLS already narrows this to their own;
     * `my_role` and `member_count` are filled in here.
     */
    public function index(Request $request): JsonResponse
    {
        $ledgers = Ledger::query()->with('owner')->orderBy('name')->get();

        $ids = $ledgers->map(fn (Ledger $ledger): int => (int) $ledger->getKey())->all();
        $roles = $this->members->rolesFor($request->user());
        $counts = $this->members->memberCounts($ids);

        $ledgers->each(function (Ledger $ledger) use ($roles, $counts): void {
            $ledger->setAttribute('my_role', $roles[(int) $ledger->getKey()] ?? null);
            $ledger->setAttribute('member_count', $counts[(int) $ledger->getKey()] ?? 0);
        });

        return LedgerResource::collection($ledgers)->response();
    }

    /**
     * Creating a ledger makes the creator its first owner, in one transaction:
     * a ledger nobody can see would be invisible to everyone, including its
     * creator.
     */
    public function store(StoreLedgerRequest $request): JsonResponse
    {
        $this->authorize('create', Ledger::class);

        $user = $request->user();

        $ledger = DB::transaction(function () use ($request, $user): Ledger {
            $ledger = Ledger::create([
                'name' => $request->string('name')->toString(),
                'owner_id' => $user->getKey(),
                'currency' => (string) config('ledger.currency'),
            ]);

            $this->members->put($ledger, $user, LedgerRole::Owner);

            return $ledger;
        });

        $this->audit->record('ledger.created', [
            'ledger_id' => (int) $ledger->getKey(),
            'name' => $ledger->name,
            'currency' => $ledger->currency,
        ]);

        $ledger->setAttribute('my_role', LedgerRole::Owner);
        $ledger->setAttribute('member_count', 1);

        return (new LedgerResource($ledger->load('owner')))->response()->setStatusCode(201);
    }
}
