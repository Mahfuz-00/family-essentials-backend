<?php

namespace App\Http\Controllers\Api\V1\Recurring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recurring\StoreRecurringRuleRequest;
use App\Http\Requests\Recurring\UpdateRecurringRuleRequest;
use App\Http\Resources\RecurringRuleResource;
use App\Models\RecurringRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recurring chores: the rules the daily `essentials:spawn-recurring` command
 * turns into fresh requests.
 *
 * The endpoints never schedule by hand — `next_run_on` is always recomputed from
 * today, so an edit re-bases the rule and the command's idempotence holds.
 */
class RecurringRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rules = RecurringRule::query()
            ->with(['assignee', 'place', 'triggerPlace'])
            ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderBy('next_run_on')
            ->orderBy('id')
            ->get();

        return RecurringRuleResource::collection($rules)->response();
    }

    public function store(StoreRecurringRuleRequest $request): JsonResponse
    {
        $this->authorize('create', RecurringRule::class);

        $rule = new RecurringRule;
        $rule->fill($request->validated());
        $rule->created_by = $request->user()->getKey();
        $rule->normaliseCadenceFields();
        $rule->scheduleNextRun();

        $rule->save();

        return (new RecurringRuleResource($this->withRelations($rule)))->response()->setStatusCode(201);
    }

    public function update(UpdateRecurringRuleRequest $request, RecurringRule $recurringRule): JsonResponse
    {
        $this->authorize('update', $recurringRule);

        $recurringRule->fill($request->validated());
        $recurringRule->normaliseCadenceFields();
        // An edit moves the clock: the next cycle is computed from today.
        $recurringRule->scheduleNextRun();

        $recurringRule->save();

        return (new RecurringRuleResource($this->withRelations($recurringRule)))->response();
    }

    public function destroy(RecurringRule $recurringRule): JsonResponse
    {
        $this->authorize('delete', $recurringRule);

        $recurringRule->delete();

        return response()->json(['message' => 'Recurring rule removed.']);
    }

    private function withRelations(RecurringRule $rule): RecurringRule
    {
        return $rule->load(['assignee', 'place', 'triggerPlace']);
    }
}
