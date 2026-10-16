<?php

namespace Tests\Feature\Voice;

use App\Enums\RequestPriority;
use App\Models\VoiceCallLog;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class OverdueEscalationCommandTest extends TestCase
{
    use CreatesTenants;

    public function test_it_only_escalates_opted_in_overdue_high_priority_requests(): void
    {
        $tenant = $this->createTenant('Household A');

        $sarah = $this->optInForVoice($this->createMember($tenant, 'member'));
        $bob = $this->createMember($tenant, 'member'); // not opted in

        $eligible = $this->createEscalationCandidate($tenant, $sarah);
        $this->createEscalationCandidate($tenant, $bob);
        $this->createEscalationCandidate($tenant, $sarah, ['priority' => RequestPriority::Normal]);
        $this->createEscalationCandidate($tenant, $sarah, ['due_at' => now()->addDay()]);

        $this->artisan('voice:escalate-overdue')->assertSuccessful();

        $this->withBypass(function () use ($eligible): void {
            $placed = VoiceCallLog::withoutGlobalScope('tenant')->where('status', '!=', 'skipped')->get();

            $this->assertCount(1, $placed);
            $this->assertSame($eligible->id, $placed->first()->request_id);

            // The opted-out candidate is refused loudly, not silently dropped.
            $this->assertDatabaseHas('voice_call_logs', ['status' => 'skipped', 'reason' => 'opt_out']);
        });
    }

    public function test_a_second_run_respects_the_cooldown(): void
    {
        $tenant = $this->createTenant('Household A');
        $sarah = $this->optInForVoice($this->createMember($tenant, 'member'));
        $this->createEscalationCandidate($tenant, $sarah);

        $this->artisan('voice:escalate-overdue')->assertSuccessful();

        // Reset only the attempt counter so the cooldown is the deciding factor.
        $this->withBypass(fn () => \App\Models\ServiceRequest::withoutGlobalScope('tenant')->update([
            'voice_escalation_attempts' => 0,
        ]));

        $this->artisan('voice:escalate-overdue')->assertSuccessful();

        $this->withBypass(fn () => $this->assertSame(
            1,
            VoiceCallLog::withoutGlobalScope('tenant')->where('status', '!=', 'skipped')->count(),
        ));
    }
}
