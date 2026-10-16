<?php

namespace App\Audit;

use App\Models\AuditEvent;
use App\Models\TenantUser;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes to the platform security ledger.
 *
 * Recording is best-effort by design: an audit failure must never break the
 * user-facing request it is observing, so everything is wrapped and logged.
 */
class AuditRecorder
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $action,
        array $payload = [],
        string $level = 'info',
        ?int $tenantId = null,
        ?int $httpStatus = null,
        ?string $actorEmail = null,
        ?User $actor = null,
    ): ?AuditEvent {
        try {
            $request = request();
            $user = $actor ?? ($request instanceof Request ? $request->user() : null);
            $payload = array_filter($payload, static fn ($value) => $value !== null);

            // Run inside a savepoint: on PostgreSQL a failed statement aborts the
            // whole transaction, so a swallowed error here would poison the
            // caller's request. A nested transaction rolls back just this write.
            return DB::transaction(fn (): AuditEvent => AuditEvent::create([
                'trace_id' => (string) Str::ulid(),
                'occurred_at' => now(),
                'level' => $level,
                'action' => $action,
                'tenant_id' => $tenantId ?? $this->context->id(),
                'actor_user_id' => $user?->getKey(),
                'actor_email' => $actorEmail ?? $user?->email,
                'actor_role' => $this->actorRole($user),
                'source_ip' => $request instanceof Request ? $request->ip() : null,
                'user_agent' => $request instanceof Request
                    ? Str::limit((string) $request->userAgent(), 500, '')
                    : null,
                'http_status' => $httpStatus,
                'payload' => $payload === [] ? null : $payload,
            ]));
        } catch (\Throwable $e) {
            Log::warning('audit.record_failed', ['action' => $action, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function actorRole(?User $user): ?string
    {
        if ($user === null) {
            return 'guest';
        }

        if ($user->is_platform_admin) {
            return 'platform_admin';
        }

        return TenantUser::query()->where('user_id', $user->getKey())->value('role');
    }
}
