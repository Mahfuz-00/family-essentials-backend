<?php

namespace App\Console\Commands;

use App\Enums\VoiceCallStatus;
use App\Models\ServiceRequest;
use App\Tenancy\Tenancy;
use App\Voice\VoiceEscalationService;
use Illuminate\Console\Command;

class EscalateOverdueRequests extends Command
{
    protected $signature = 'voice:escalate-overdue {--limit=100}';

    protected $description = 'Place voice escalation calls for overdue high-priority requests.';

    public function handle(VoiceEscalationService $service): int
    {
        $ids = Tenancy::bypass(fn () => ServiceRequest::withoutGlobalScope('tenant')
            ->whereIn('status', ['pending', 'accepted', 'in_progress'])
            ->whereIn('priority', ['high', 'urgent'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotNull('assignee_id')
            ->where('voice_escalation_attempts', '<', (int) config('voice.max_attempts_per_request'))
            ->orderBy('due_at')
            ->limit((int) $this->option('limit'))
            ->pluck('id')
            ->all());

        $placed = 0;

        foreach ($ids as $id) {
            $tenantId = Tenancy::bypass(
                fn () => ServiceRequest::withoutGlobalScope('tenant')->whereKey($id)->value('tenant_id')
            );

            if ($tenantId === null) {
                continue;
            }

            $log = Tenancy::forTenant((int) $tenantId, function () use ($id, $service): ?\App\Models\VoiceCallLog {
                $request = ServiceRequest::query()->find($id);

                return $request === null ? null : $service->escalate($request);
            });

            if ($log !== null && $log->status !== VoiceCallStatus::Skipped) {
                $placed++;
            }
        }

        $this->info(sprintf('Escalated %d call(s) from %d candidate(s).', $placed, count($ids)));

        return self::SUCCESS;
    }
}
