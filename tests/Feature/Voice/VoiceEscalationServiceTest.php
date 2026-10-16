<?php

namespace Tests\Feature\Voice;

use App\Models\ServiceRequest;
use App\Models\VoiceCallLog;
use App\Voice\VoiceCaller;
use App\Voice\VoiceEscalationService;
use RuntimeException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class VoiceEscalationServiceTest extends TestCase
{
    use CreatesTenants;

    public function test_it_places_a_call_and_records_the_attempt(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->optInForVoice($this->createMember($tenant, 'member'));
        $request = $this->createEscalationCandidate($tenant, $user);

        $log = $this->asTenant($tenant->id, fn () => app(VoiceEscalationService::class)->escalate($request));

        $this->assertSame('ringing', $log->status->value);
        $this->assertSame('fake', $log->provider);
        $this->assertSame('overdue', $log->reason);

        $this->withBypass(function () use ($request, $user): void {
            $fresh = ServiceRequest::withoutGlobalScope('tenant')->findOrFail($request->id);

            $this->assertSame(1, $fresh->voice_escalation_attempts);
            $this->assertNotNull($fresh->last_voice_attempt_at);

            $this->assertDatabaseHas('voice_call_logs', [
                'request_id' => $request->id,
                'user_id' => $user->id,
                'status' => 'ringing',
            ]);
        });
    }

    public function test_the_idempotency_key_prevents_a_duplicate_call(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->optInForVoice($this->createMember($tenant, 'member'));
        $request = $this->createEscalationCandidate($tenant, $user);

        $fake = app(VoiceCaller::class);

        $first = $this->asTenant($tenant->id, fn () => app(VoiceEscalationService::class)->escalate($request));

        // Simulate a duplicate delivery of the same attempt: clear the retry
        // bookkeeping so the policy passes, and the deterministic idempotency
        // key must then resolve to the existing call rather than dialling again.
        $this->withBypass(fn () => ServiceRequest::withoutGlobalScope('tenant')
            ->whereKey($request->id)
            ->update(['voice_escalation_attempts' => 0, 'last_voice_attempt_at' => null]));

        $second = $this->asTenant($tenant->id, function () use ($request) {
            $fresh = ServiceRequest::query()->findOrFail($request->id);

            return app(VoiceEscalationService::class)->escalate($fresh);
        });

        $this->assertSame($first->id, $second->id);
        $this->assertCount(1, $fake->calls);
        $this->withBypass(fn () => $this->assertSame(1, VoiceCallLog::withoutGlobalScope('tenant')->count()));
    }

    public function test_a_provider_failure_is_recorded_and_not_counted_as_an_attempt(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->optInForVoice($this->createMember($tenant, 'member'));
        $request = $this->createEscalationCandidate($tenant, $user);

        $fake = app(VoiceCaller::class);
        $fake->failure = new RuntimeException('provider exploded');

        $log = $this->asTenant($tenant->id, fn () => app(VoiceEscalationService::class)->escalate($request));

        $this->assertSame('failed', $log->status->value);
        $this->assertSame('provider exploded', $log->error);

        $this->withBypass(fn () => $this->assertSame(
            0,
            ServiceRequest::withoutGlobalScope('tenant')->findOrFail($request->id)->voice_escalation_attempts,
        ));
    }

    public function test_a_blocked_escalation_records_a_skipped_row_with_the_reason(): void
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->createMember($tenant, 'member'); // not opted in
        $request = $this->createEscalationCandidate($tenant, $user);

        $log = $this->asTenant($tenant->id, fn () => app(VoiceEscalationService::class)->escalate($request));

        $this->assertSame('skipped', $log->status->value);
        $this->assertSame('opt_out', $log->reason);
        $this->assertCount(0, app(VoiceCaller::class)->calls);
    }
}
