<?php

namespace App\Voice;

use App\Enums\VoiceCallStatus;
use App\Enums\VoiceEscalationReason;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VoiceCallLog;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Places an escalation call: policy gate → idempotent log row → provider →
 * audit. The unique idempotency key is what actually prevents duplicate calls.
 */
class VoiceEscalationService
{
    public function __construct(
        private readonly VoiceCaller $caller,
        private readonly VoiceEscalationPolicy $policy,
    ) {}

    public function escalate(ServiceRequest $request, bool $manual = false): VoiceCallLog
    {
        $recipient = $request->assignee ?? $request->requester;

        if ($recipient === null) {
            return $this->recordSkipped($request, null, VoiceEscalationReason::NoPhone);
        }

        $reason = $this->policy->blockingReason($request, $recipient, $manual);

        if ($reason !== null) {
            return $this->recordSkipped($request, $recipient, $reason);
        }

        $attempt = (int) $request->voice_escalation_attempts + 1;
        $key = sprintf(
            'voice:%d:%d:%d',
            $request->getKey(),
            $attempt,
            $request->due_at?->timestamp ?? 0,
        );

        $existing = VoiceCallLog::query()->where('idempotency_key', $key)->first();

        if ($existing !== null) {
            return $existing;
        }

        $log = VoiceCallLog::create([
            'tenant_id' => $request->tenant_id,
            'request_id' => $request->getKey(),
            'user_id' => $recipient->getKey(),
            'idempotency_key' => $key,
            'status' => VoiceCallStatus::Queued,
            'reason' => $manual ? VoiceEscalationReason::Manual->value : VoiceEscalationReason::Overdue->value,
            'attempts' => $attempt,
            'initiated_at' => Carbon::now(),
        ]);

        try {
            $result = $this->caller->place($recipient, $this->message($request), $key);
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => VoiceCallStatus::Failed,
                'error' => $exception->getMessage(),
            ])->save();

            return $log;
        }

        $log->forceFill([
            'provider' => $result->provider,
            'provider_call_id' => $result->providerCallId,
            'status' => $result->status,
        ])->save();

        $request->forceFill([
            'voice_escalation_attempts' => $attempt,
            'last_voice_attempt_at' => Carbon::now(),
        ])->save();

        return $log;
    }

    private function message(ServiceRequest $request): string
    {
        $parts = array_values(array_filter([$request->quantity, $request->store_name]));

        return trim(sprintf(
            'Reminder from Family Essentials: %s%s',
            $request->title,
            $parts === [] ? '' : ' ('.implode(', ', $parts).')',
        ));
    }

    private function recordSkipped(
        ServiceRequest $request,
        ?User $recipient,
        VoiceEscalationReason $reason,
    ): VoiceCallLog {
        return VoiceCallLog::create([
            'tenant_id' => $request->tenant_id,
            'request_id' => $request->getKey(),
            'user_id' => $recipient?->getKey(),
            'idempotency_key' => sprintf('voice-skip:%d:%s:%d', $request->getKey(), $reason->value, now()->timestamp),
            'status' => VoiceCallStatus::Skipped,
            'reason' => $reason->value,
            'attempts' => 0,
        ]);
    }
}
