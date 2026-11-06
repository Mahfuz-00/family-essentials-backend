<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Ops\HealthReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * System health for the landlord console.
 *
 * Everything here is measured, not asserted: what cannot be checked reports as a
 * failure with its reason.
 */
class HealthController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(HealthReport $report): JsonResponse
    {
        return response()->json([
            'report' => $report->run(),
            'history' => $report->history(24),
            'failed_jobs' => $this->failedJobs(),
            'incidents' => IncidentResource::collection(
                Incident::query()->with('author:id,name')->orderByDesc('started_at')->limit(20)->get()
            ),
        ]);
    }

    /**
     * Re-queue one failed job. Reading the failures is useful; being able to act
     * on them is what makes the panel operational.
     */
    public function retryFailedJob(string $id): JsonResponse
    {
        $job = DB::table('failed_jobs')->where('id', $id)->first();

        abort_if($job === null, 404, 'That failed job no longer exists.');

        try {
            Artisan::call('queue:retry', ['id' => [$job->uuid]]);
        } catch (Throwable $e) {
            $this->audit->record('ops.failed_job.retry_failed', [
                'job' => $job->uuid,
                'error' => $e->getMessage(),
            ], 'warning');

            abort(422, 'The job could not be requeued: '.$e->getMessage());
        }

        $this->audit->record('ops.failed_job.retried', [
            'job' => $job->uuid,
            'queue' => $job->queue,
        ], 'warning');

        return response()->json([
            'message' => 'Job requeued.',
            'uuid' => $job->uuid,
        ]);
    }

    public function retryAllFailedJobs(): JsonResponse
    {
        $count = DB::table('failed_jobs')->count();

        if ($count > 0) {
            Artisan::call('queue:retry', ['id' => ['all']]);
            $this->audit->record('ops.failed_jobs.retried_all', ['count' => $count], 'warning');
        }

        return response()->json([
            'message' => $count > 0 ? "Queued {$count} failed jobs for retry." : 'No failed jobs in queue.',
            'count' => $count,
        ]);
    }

    public function flushFailedJobs(): JsonResponse
    {
        $count = DB::table('failed_jobs')->count();

        if ($count > 0) {
            DB::table('failed_jobs')->delete();
            $this->audit->record('ops.failed_jobs.flushed', ['count' => $count], 'warning');
        }

        return response()->json([
            'message' => "Flushed {$count} failed jobs.",
            'count' => $count,
        ]);
    }

    public function clearCache(Request $request): JsonResponse
    {
        $type = $request->input('type', 'all');

        try {
            \Illuminate\Support\Facades\Cache::flush();
            $this->audit->record('ops.cache.cleared', ['type' => $type], 'info');

            return response()->json([
                'success' => true,
                'message' => 'Platform cache flushed successfully.',
                'cleared_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to flush cache: '.$e->getMessage(),
            ], 500);
        }
    }

    public function maintenance(): JsonResponse
    {
        $status = \App\Models\PlatformSetting::get('maintenance_mode', [
            'enabled' => false,
            'message' => 'Platform is operating normally.',
            'started_at' => null,
            'allowed_ips' => [],
        ]);

        return response()->json(['data' => $status]);
    }

    public function toggleMaintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'message' => ['nullable', 'string'],
            'allowed_ips' => ['nullable', 'array'],
        ]);

        $status = [
            'enabled' => $validated['enabled'],
            'message' => $validated['message'] ?? 'FairShare is undergoing scheduled maintenance.',
            'started_at' => $validated['enabled'] ? now()->toIso8601String() : null,
            'allowed_ips' => $validated['allowed_ips'] ?? [],
        ];

        \App\Models\PlatformSetting::set('maintenance_mode', $status);

        $this->audit->record(
            $validated['enabled'] ? 'ops.maintenance.enabled' : 'ops.maintenance.disabled',
            ['message' => $status['message']],
            $validated['enabled'] ? 'warning' : 'info'
        );

        return response()->json([
            'message' => $validated['enabled'] ? 'Maintenance mode enabled.' : 'Maintenance mode disabled.',
            'data' => $status,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function failedJobs(): array
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get(['id', 'uuid', 'queue', 'failed_at', 'exception'])
            ->map(fn ($job): array => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at,
                // First line only: the full trace is enormous and unreadable here.
                'reason' => Str::limit(trim(explode("\n", (string) $job->exception)[0]), 200),
            ])
            ->all();
    }
}
