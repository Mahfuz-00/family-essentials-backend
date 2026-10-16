<?php

namespace Tests\Feature\Voice;

use App\Enums\VoiceEscalationReason;
use App\Voice\VoiceEscalationPolicy;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class VoiceConsentTest extends TestCase
{
    use CreatesTenants;

    public function test_opting_in_requires_a_verified_phone(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $member->forceFill(['phone_verified_at' => null])->save();

        $this->withToken($this->tokenFor($member))
            ->patchJson('/api/v1/auth/voice-consent', ['voice_opt_in' => true])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Verify your phone number before enabling voice calls.');
    }

    public function test_a_member_can_opt_in_and_out(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $member->forceFill(['phone' => '+15550001234', 'phone_verified_at' => now()])->save();
        $token = $this->tokenFor($member);

        $this->withToken($token)
            ->patchJson('/api/v1/auth/voice-consent', ['voice_opt_in' => true])
            ->assertOk()
            ->assertJsonPath('voice_opt_in', true);

        $this->assertTrue((bool) $member->fresh()->voice_opt_in);

        $this->withToken($token)
            ->patchJson('/api/v1/auth/voice-consent', ['voice_opt_in' => false])
            ->assertOk()
            ->assertJsonPath('voice_opt_in', false);

        $this->assertFalse((bool) $member->fresh()->voice_opt_in);
    }

    public function test_opting_out_blocks_escalation_with_an_opt_out_reason(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $member->forceFill(['phone_verified_at' => now()])->save();

        $request = $this->createEscalationCandidate($tenant, $member);

        $this->withToken($this->tokenFor($member))
            ->patchJson('/api/v1/auth/voice-consent', ['voice_opt_in' => false])
            ->assertOk();

        $reason = $this->asTenant(
            $tenant->id,
            fn () => app(VoiceEscalationPolicy::class)->blockingReason($request, $member->fresh()),
        );

        $this->assertSame(VoiceEscalationReason::OptOut, $reason);
    }
}
