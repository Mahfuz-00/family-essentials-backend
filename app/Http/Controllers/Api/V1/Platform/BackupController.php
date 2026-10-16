<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Resources\BackupResource;
use App\Models\Backup;
use App\Ops\Backups\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backups and disaster recovery for the landlord console.
 *
 * The console reports what is actually possible here — the deposit tool, the
 * connection the dump is taken as, the writability of the destination, and how
 * old the newest backup is against the recovery point objective. It does not
 * claim the platform is ready because a page exists.
 */
class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly AuditRecorder $audit,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'readiness' => $this->backups->readiness(),
            'backups' => BackupResource::collection(
                Backup::query()->with('creator:id,name')->orderByDesc('started_at')->limit(50)->get()
            ),
            'runbook' => $this->runbook(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $backup = $this->backups->run('manual', $request->user()?->getKey());

        if ($backup->status === 'completed') {
            $backup = $this->backups->verify($backup);
        }

        // One ledger row per run, carrying the verification outcome: a dump that
        // was taken but not proven intact is not the same event.
        $this->audit->record('ops.backup.created', [
            'backup' => $backup->filename,
            'status' => $backup->status,
            'verified' => $backup->status === 'completed' ? $backup->verify_error === null : null,
        ], $backup->status === 'completed' ? 'info' : 'warning');

        return (new BackupResource($backup->load('creator:id,name')))
            ->response()
            ->setStatusCode($backup->status === 'completed' ? 201 : 202);
    }

    public function verify(Backup $backup): JsonResponse
    {
        $verified = $this->backups->verify($backup);

        $this->audit->record('ops.backup.verified', [
            'backup' => $verified->filename,
            'ok' => $verified->verify_error === null,
        ], $verified->verify_error === null ? 'info' : 'warning');

        return (new BackupResource($verified->load('creator:id,name')))->response();
    }

    public function destroy(Backup $backup): JsonResponse
    {
        if ($backup->status === 'running') {
            abort(422, 'That backup is still running.');
        }

        Storage::disk($backup->disk)->delete($backup->filename);

        $this->audit->record('ops.backup.deleted', [
            'backup' => $backup->filename,
        ], 'warning');

        $backup->delete();

        return response()->json(['message' => 'Backup deleted.']);
    }

    public function download(Backup $backup): StreamedResponse
    {
        abort_unless($backup->status === Backup::COMPLETED, 404, 'That backup did not complete.');

        $disk = Storage::disk($backup->disk);

        abort_unless($disk->exists($backup->filename), 404, 'The file is no longer on disk.');

        // Exfiltrating a whole database is worth recording.
        $this->audit->record('ops.backup.downloaded', [
            'backup' => $backup->filename,
        ], 'warning');

        return $disk->download($backup->filename, basename($backup->filename));
    }

    /**
     * The restore procedure, generated from the live configuration so the
     * commands name the right database and file.
     *
     * @return array<string, mixed>
     */
    private function runbook(): array
    {
        $config = config('database.connections.'.config('backup.connection'), []);
        $database = (string) ($config['database'] ?? 'family_essentials');
        $directory = (string) config('backup.directory');

        return [
            'iaas' => 'The dump is a logical backup of the database only. Uploaded files on the app disk are not in it.',
            'rpo_hours' => (int) config('backup.rpo_hours'),
            'rto_minutes' => (int) config('backup.rto_minutes'),
            'retention_days' => (int) config('backup.retention_days'),
            'steps' => [
                [
                    'label' => 'Inspect the dump without restoring it',
                    'command' => 'pg_restore --list storage/app/private/'.$directory.'/<file>.dump',
                ],
                [
                    'label' => 'Restore into a scratch database first',
                    'command' => 'createdb '.$database.'_restore && pg_restore --dbname='.$database.'_restore --clean --if-exists --no-owner --no-acl storage/app/private/'.$directory.'/<file>.dump',
                ],
                [
                    'label' => 'Check the restored data before switching over',
                    'command' => 'psql '.$database.'_restore -c "select count(*) from audit_events" -c "select count(*) from tenants"',
                ],
                [
                    'label' => 'Promote, then re-point the application',
                    'command' => "-- stop writes, rename {$database} to {$database}_broken, rename {$database}_restore to {$database}, restart the queue",
                ],
                [
                    'label' => 'Record what happened in the incident log',
                    'command' => 'Platform Admin → System Health → Incidents',
                ],
            ],
        ];
    }
}
