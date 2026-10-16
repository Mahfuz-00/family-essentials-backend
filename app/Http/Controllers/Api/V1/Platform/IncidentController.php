<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The incident log.
 *
 * Operator-authored on purpose: nothing here is auto-detected, and the console
 * says so. The alternative — inferring incidents from thresholds — would invent
 * history nobody wrote.
 */
class IncidentController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(): JsonResponse
    {
        return IncidentResource::collection(
            Incident::query()->with('author:id,name')->orderByDesc('started_at')->limit(50)->get()
        )->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'severity' => ['required', 'in:'.implode(',', Incident::SEVERITIES)],
            'status' => ['sometimes', 'in:'.implode(',', Incident::STATUSES)],
            'body' => ['nullable', 'string', 'max:4000'],
            'started_at' => ['sometimes', 'date'],
        ]);

        $incident = Incident::create([
            'title' => $validated['title'],
            'severity' => $validated['severity'],
            'status' => $validated['status'] ?? 'investigating',
            'body' => $validated['body'] ?? null,
            'started_at' => $validated['started_at'] ?? now(),
            'created_by' => $request->user()->getKey(),
        ]);

        $this->audit->record('ops.incident.opened', [
            'incident' => $incident->title,
            'severity' => $incident->severity,
        ], $incident->severity === 'critical' ? 'critical' : 'warning');

        return (new IncidentResource($incident->load('author:id,name')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Incident $incident): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:'.implode(',', Incident::STATUSES)],
            'severity' => ['sometimes', 'in:'.implode(',', Incident::SEVERITIES)],
            'body' => ['nullable', 'string', 'max:4000'],
        ]);

        // Resolving stamps the end; re-opening clears it, so the log stays truthful.
        if (($validated['status'] ?? null) === 'resolved') {
            $validated['resolved_at'] = now();
        } elseif (($validated['status'] ?? null) !== null && $incident->resolved_at !== null) {
            $validated['resolved_at'] = null;
        }

        $previous = $incident->status;
        $incident->update($validated);

        $this->audit->record('ops.incident.updated', [
            'incident' => $incident->title,
            'from' => $previous,
            'to' => $incident->status,
        ], $incident->status === 'resolved' ? 'info' : 'warning');

        return (new IncidentResource($incident->load('author:id,name')))->response();
    }
}
