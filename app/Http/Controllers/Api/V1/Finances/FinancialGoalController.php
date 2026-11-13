<?php

namespace App\Http\Controllers\Api\V1\Finances;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Resources\FinancialGoalResource;
use App\Models\FinancialGoal;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialGoalController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Request $request): JsonResponse
    {
        $goals = FinancialGoal::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderBy('target_date')
            ->orderBy('name')
            ->get();

        return FinancialGoalResource::collection($goals)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'target_amount_minor' => ['required', 'integer', 'min:1'],
            'current_amount_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'target_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:active,achieved,paused'],
            'notes' => ['nullable', 'string'],
        ]);

        $tenant = app(TenantContext::class)->tenant();
        $validated['currency'] = $validated['currency'] ?? ($tenant?->settings['currency'] ?? 'USD');
        $validated['created_by_id'] = $request->user()?->getKey();

        $goal = FinancialGoal::create($validated);

        $this->audit->record('finance.goal_created', [
            'goal_id' => $goal->id,
            'name' => $goal->name,
            'target_amount_minor' => $goal->target_amount_minor,
        ]);

        return (new FinancialGoalResource($goal))->response()->setStatusCode(201);
    }

    public function show(FinancialGoal $goal): JsonResponse
    {
        return (new FinancialGoalResource($goal))->response();
    }

    public function update(Request $request, FinancialGoal $goal): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'target_amount_minor' => ['sometimes', 'required', 'integer', 'min:1'],
            'current_amount_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'target_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:active,achieved,paused'],
            'notes' => ['nullable', 'string'],
        ]);

        $goal->update($validated);

        $this->audit->record('finance.goal_updated', [
            'goal_id' => $goal->id,
            'name' => $goal->name,
            'current_amount_minor' => $goal->current_amount_minor,
        ]);

        return (new FinancialGoalResource($goal))->response();
    }

    public function destroy(FinancialGoal $goal): JsonResponse
    {
        $id = $goal->id;
        $name = $goal->name;
        $goal->delete();

        $this->audit->record('finance.goal_deleted', [
            'goal_id' => $id,
            'name' => $name,
        ]);

        return response()->json(['message' => 'Goal removed.']);
    }
}
