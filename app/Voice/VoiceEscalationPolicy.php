<?php

namespace App\Voice;

use App\Enums\RequestPriority;
use App\Enums\VoiceEscalationReason;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VoiceCallLog;
use App\Reminders\QuietHours;
use Illuminate\Support\Carbon;

/**
 * The single gate in front of every voice call. Returns null when a call may be
 * placed, or the reason it must be refused.
 */
class VoiceEscalationPolicy
{
    public function __construct(private readonly QuietHours $quietHours) {}

    public function blockingReason(ServiceRequest $request, User $recipient, bool $manual = false): ?VoiceEscalationReason
    {
        if (! $request->status->isOpen()) {
            return VoiceEscalationReason::AlreadyClosed;
        }

        // A manual trigger may bypass the priority gate, but never consent,
        // quiet hours or the retry bounds.
        if (! $manual && ! $this->isHighPriority($request)) {
            return VoiceEscalationReason::LowPriority;
        }

        if (! $recipient->voice_opt_in) {
            return VoiceEscalationReason::OptOut;
        }

        if (blank($recipient->phone) || $recipient->phone_verified_at === null) {
            return VoiceEscalationReason::NoPhone;
        }

        if ($this->quietHours->isQuiet($request->tenant, $recipient, Carbon::now())) {
            return VoiceEscalationReason::QuietHours;
        }

        if ((int) $request->voice_escalation_attempts >= (int) config('voice.max_attempts_per_request')) {
            return VoiceEscalationReason::RetryLimit;
        }

        if ($this->dailyBudgetSpent()) {
            return VoiceEscalationReason::RetryLimit;
        }

        if ($this->withinCooldown($request)) {
            return VoiceEscalationReason::RetryLimit;
        }

        return null;
    }

    private function isHighPriority(ServiceRequest $request): bool
    {
        return in_array($request->priority, [RequestPriority::High, RequestPriority::Urgent], true);
    }

    private function dailyBudgetSpent(): bool
    {
        $budget = (int) config('voice.daily_budget');

        if ($budget <= 0) {
            return false;
        }

        return VoiceCallLog::query()
            ->whereDate('created_at', now()->toDateString())
            ->whereNotIn('status', ['skipped'])
            ->count() >= $budget;
    }

    private function withinCooldown(ServiceRequest $request): bool
    {
        $cooldown = (int) config('voice.cooldown_minutes');

        if ($cooldown <= 0 || $request->last_voice_attempt_at === null) {
            return false;
        }

        return $request->last_voice_attempt_at->gt(now()->subMinutes($cooldown));
    }
}
