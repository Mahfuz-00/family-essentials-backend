<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\AuditLogIndexRequest;
use App\Http\Resources\AuditEventResource;
use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): JsonResponse
    {
        $events = $this->filtered($request)
            ->with('tenant:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return AuditEventResource::collection($events)->response();
    }

    public function show(AuditEvent $auditEvent): JsonResponse
    {
        return (new AuditEventResource($auditEvent->load('tenant:id,name')))->response();
    }

    public function export(AuditLogIndexRequest $request): StreamedResponse
    {
        $query = $this->filtered($request);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'trace_id', 'occurred_at', 'level', 'action',
                'tenant_id', 'actor_email', 'actor_role', 'source_ip', 'http_status',
            ]);

            $query->orderByDesc('occurred_at')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->trace_id,
                        $row->occurred_at?->toISOString(),
                        $row->level,
                        $row->action,
                        $row->tenant_id,
                        $row->actor_email,
                        $row->actor_role,
                        $row->source_ip,
                        $row->http_status,
                    ]);
                }
            });

            fclose($out);
        }, 'audit-log.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return Builder<AuditEvent>
     */
    private function filtered(AuditLogIndexRequest $request): Builder
    {
        return AuditEvent::query()
            ->when($request->filled('level'), fn (Builder $q) => $q->where('level', $request->string('level')))
            ->when($request->filled('action'), fn (Builder $q) => $q->where('action', 'like', $request->string('action').'%'))
            ->when($request->filled('tenant_id'), fn (Builder $q) => $q->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('from'), fn (Builder $q) => $q->where('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('occurred_at', '<=', $request->date('to')))
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $needle = '%'.$request->string('q').'%';

                $q->where(function (Builder $inner) use ($needle): void {
                    $inner->where('actor_email', 'like', $needle)
                        ->orWhere('action', 'like', $needle)
                        ->orWhere('source_ip', 'like', $needle)
                        ->orWhere('trace_id', 'like', $needle);
                });
            });
    }
}
