<?php

namespace App\Http\Controllers\Api\V1\Pantry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Essentials\RestockEssentialRequest;
use App\Http\Requests\Essentials\StoreEssentialRequest;
use App\Http\Requests\Essentials\UpdateEssentialRequest;
use App\Http\Resources\EssentialResource;
use App\Models\Essential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recurring household essentials with restock cadence and safety stock.
 * Feeds the mobile "pantry pulse" and the safety-stock alert.
 */
class EssentialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $essentials = Essential::query()
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->orderBy('name')
            ->get();

        // The model owns the due rule, so evaluate once and reuse.
        $due = $essentials->filter(fn (Essential $item) => $item->isDue());

        // The pulse numbers always describe the whole pantry, not the filtered view.
        $summary = [
            'total' => $essentials->count(),
            'due' => $due->count(),
            'safety_stock' => $due->filter(fn (Essential $item) => $item->dueReason() === 'safety_stock')->count(),
            'cadence' => $due->filter(fn (Essential $item) => $item->dueReason() === 'cadence')->count(),
        ];

        if ($request->boolean('due_only')) {
            $essentials = $due->values();
        } elseif ($request->filled('reason')) {
            $reason = $request->string('reason')->toString();
            $essentials = $essentials->filter(fn (Essential $item) => $item->dueReason() === $reason)->values();
        } else {
            // Due first, then alphabetical.
            $essentials = $essentials->sortByDesc(fn (Essential $item) => $item->isDue())->values();
        }

        return response()->json([
            'summary' => $summary,
            'data' => EssentialResource::collection($essentials),
        ]);
    }

    public function store(StoreEssentialRequest $request): JsonResponse
    {
        $this->authorize('create', Essential::class);

        $essential = Essential::create([
            ...$request->safe()->only([
                'name', 'category', 'default_store', 'pack_size',
                'cadence_days', 'threshold', 'stock_level',
            ]),
            'created_by' => $request->user()->getKey(),
        ]);

        return (new EssentialResource($essential))->response()->setStatusCode(201);
    }

    public function update(UpdateEssentialRequest $request, Essential $essential): JsonResponse
    {
        $this->authorize('update', $essential);

        $essential->update($request->validated());

        return (new EssentialResource($essential))->response();
    }

    public function destroy(Essential $essential): JsonResponse
    {
        $this->authorize('delete', $essential);

        $essential->delete();

        return response()->json(['message' => 'Essential removed.']);
    }

    /**
     * Adds stock and resets the cadence clock.
     */
    public function restock(RestockEssentialRequest $request, Essential $essential): JsonResponse
    {
        $this->authorize('restock', $essential);

        $essential->update([
            'stock_level' => (int) $essential->stock_level + $request->integer('quantity'),
            'last_restocked_at' => now(),
        ]);

        return (new EssentialResource($essential))->response();
    }
}
