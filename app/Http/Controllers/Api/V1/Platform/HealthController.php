<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Ops\HealthReport;
use Illuminate\Http\JsonResponse;
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
