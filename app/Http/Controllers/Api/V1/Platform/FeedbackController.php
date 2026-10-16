<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformFeedbackResource;
use App\Models\FeedbackReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The feedback inbox: every household's reports, in one queue.
 *
 * Reads across tenants through the platform admin's RLS bypass, and every triage
 * change is written to the security ledger — a household is told when its report
 * is resolved, so that change must be traceable.
 */
class FeedbackController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = FeedbackReport::query()
            ->with(['tenant:id,name,subscription_tier,status', 'reporter:id,name,email', 'resolver:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->boolean('open_only'), fn ($q) => $q->open());

        $reports = (clone $query)
            ->orderByDesc('created_at')
            ->limit(min(100, max(1, $request->integer('per_page', 50))))
            ->get();

        return response()->json([
            'data' => PlatformFeedbackResource::collection($reports),
            'summary' => $this->summary(),
        ]);
    }

    public function update(Request $request, string $feedback): JsonResponse
    {
        // Resolved explicitly rather than by route–model binding: bindings are
        // substituted before this route's middleware runs, and the tenant global
        // scope would fail closed on a cross-tenant row (a 404 for a real report).
        $feedback = FeedbackReport::query()->withoutGlobalScope('tenant')->findOrFail($feedback);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(FeedbackReport::STATUSES)],
            'priority' => ['sometimes', Rule::in(FeedbackReport::PRIORITIES)],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $before = $feedback->only(['status', 'priority']);

        $resolved = in_array($validated['status'] ?? null, ['resolved', 'wont_fix'], true);

        if ($resolved) {
            $validated['resolved_at'] = now();
            $validated['resolved_by'] = $request->user()->getKey();
        } elseif (array_key_exists('status', $validated) && $feedback->resolved_at !== null) {
            // Re-opened: the household must not keep seeing a resolution.
            $validated['resolved_at'] = null;
            $validated['resolved_by'] = null;
        }

        $feedback->update($validated);

        $this->audit->record('ops.feedback.triaged', [
            'report' => $feedback->subject,
            'tenant_id' => $feedback->tenant_id,
            'before' => $before,
            'after' => $feedback->only(['status', 'priority']),
        ], $resolved ? 'info' : 'warning');

        return (new PlatformFeedbackResource(
            $feedback->load(['tenant:id,name,subscription_tier,status', 'reporter:id,name,email', 'resolver:id,name'])
        ))->response();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        $byStatus = FeedbackReport::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byType = FeedbackReport::query()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $oldest = FeedbackReport::query()->open()->min('created_at');

        return [
            'open' => FeedbackReport::query()->open()->count(),
            'total' => FeedbackReport::query()->count(),
            'critical_open' => FeedbackReport::query()
                ->open()
                ->where('priority', 'critical')
                ->count(),
            'unseen' => (int) ($byStatus['new'] ?? 0),
            'oldest_open_at' => $oldest,
            'by_status' => $byStatus,
            'by_type' => $byType,
        ];
    }
}
