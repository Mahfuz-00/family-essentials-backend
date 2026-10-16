<?php

namespace Tests\Feature\Voice;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\VoiceEscalationReason;
use App\Models\Tenant;
use App\Voice\VoiceEscalationPolicy;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class VoiceEscalationPolicyTest extends TestCase
{
    use CreatesTenants;

    private function reason(Tenant $tenant, callable $callback): ?VoiceEscalationReason
    {
        return $this->asTenant($tenant->id, fn () => $callback());
    }

    public function test_it_allows_an_opted_in_overdue_high_priority_request(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->optInForVoice($this->createMember($tenant, 'member'));
        $request = $this->createEscalationCandidate($tenant, $user);

        $this->assertNull($this->reason(
            $tenant,
            fn () => app(VoiceEscalationPolicy::class)->blockingReason($request, $user),
        ));
    }

    public function test_it_blocks_when_the_assignee_has_not_opted_in(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->createMember($tenant, 'member');
        $request = $this->createEscalationCandidate($tenant, $user);

        $this->assertSame(VoiceEscalationReason::OptOut, $this->reason(
            $tenant,
            fn () => app(VoiceEscalationPolicy::class)->blockingReason($request, $user),
        ));
    }

    public function test_it_blocks_an_unverified_phone(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->optInForVoice($this->createMember($tenant, 'member'));
        $user->forceFill(['phone_verified_at' => null])->save();

        $request = $this->createEscalationCandidate($tenant, $user);

        $this->assertSame(VoiceEscalationReason::NoPhone, $this->reason(
            $tenant,
            fn () => app(VoiceEscalationPolicy::class)->blockingReason($request, $user),
        ));
    }

    public function test_it_blocks_low_priority_but_a_manual_trigger_bypasses_that(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->optInForVoice($this->createMember($tenant, 'member'));
        $request = $this->createEscalationCandidate($tenant, $user, ['priority' => RequestPriority::Normal]);

        $this->assertSame(VoiceEscalationReason::LowPriority, $this->reason(
            $tenant,
            fn () => app(VoiceEscalationPolicy::class)->blockingReason($request, $user),
        ));

        $this->assertNull($this->reason(
            $tenant,
            fn () => app(VoiceEscalationPolicy::class)->blockingReason($request, $user, manual: true),
        ));
    }

    public function test_it_respects_quiet_hours(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->optInForVoice($this->createMember($tenant, 'member'));
        $request = $this->createEscalationCandidate($tenant, $user);

        $this->withBypass(fn () => Tenant::withoutGlobalScope('tenant')->whereKey($tenant->id)->update([
            'settings' => ['quiet_hours' => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']],
        ]));

        $this->assertSame(VoiceEscalationReason::QuietHours, $this->reason(
            $tenant,
            fn () => app(VoiceEscalationPolicy::class)->blockingReason($request, $user),
        ));
    }

    public function test_it_enforces_the_per_request_attempt_limit(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->optInForVoice($this->createMember($tenant, 'member'));
        $request = $this->createEscalationCandidate($tenant, $user, ['voice_escalation_attempts' => 2]);

        $this->assertSame(VoiceEscalationReason::RetryLimit, $this->reason(
            $tenant,
            fn () => app(VoiceEscalationPolicy::class)->blockingReason($request, $user),
        ));
    }

    public function test_it_enforces_the_cooldown(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->optInForVoice($this->createMember($tenant, 'member'));
        $request = $this->createEscalationCandidate($tenant, $user, ['last_voice_attempt_at' => now()->subMinutes(5)]);

        $this->assertSame(VoiceEscalationReason::RetryLimit, $this->reason(
            $tenant,
            fn () => app(VoiceEscalationPolicy::class)->blockingReason($request, $user),
        ));
    }

    public function test_it_blocks_a_closed_request(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->optInForVoice($this->createMember($tenant, 'member'));
        $request = $this->createEscalationCandidate($tenant, $user, [
            'status' => RequestStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->assertSame(VoiceEscalationReason::AlreadyClosed, $this->reason(
            $tenant,
            fn () => app(VoiceEscalationPolicy::class)->blockingReason($request, $user),
        ));
    }
}
