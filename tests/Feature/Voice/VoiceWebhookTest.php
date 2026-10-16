<?php

namespace Tests\Feature\Voice;

use App\Models\VoiceCallLog;
use App\Models\VoiceWebhookEvent;
use App\Voice\VoiceEscalationService;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class VoiceWebhookTest extends TestCase
{
    use CreatesTenants;

    /**
     * @return array{0: string, 1: string} [body, signature]
     */
    private function signed(array $data): array
    {
        $body = json_encode($data);

        return [$body, hash_hmac('sha256', $body, (string) config('voice.webhook_secret'))];
    }

    private function postWebhook(string $body, string $signature): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/api/v1/webhooks/voice/fake', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_VOICE_SIGNATURE' => $signature,
        ], $body);
    }

    private function startedCall(): VoiceCallLog
    {
        $tenant = $this->createTenant('Household A');
        $user = $this->optInForVoice($this->createMember($tenant, 'member'));
        $request = $this->createEscalationCandidate($tenant, $user);

        return $this->asTenant($tenant->id, fn () => app(VoiceEscalationService::class)->escalate($request));
    }

    public function test_it_rejects_a_missing_or_invalid_signature(): void
    {
        [$body] = $this->signed(['event_id' => 'evt_1', 'provider_call_id' => 'x', 'status' => 'completed']);

        $this->postWebhook($body, '')->assertStatus(403);
        $this->postWebhook($body, 'nonsense')->assertStatus(403);
    }

    public function test_a_valid_signature_applies_the_outcome(): void
    {
        $call = $this->startedCall();

        [$body, $signature] = $this->signed([
            'event_id' => 'evt_answered',
            'provider_call_id' => $call->provider_call_id,
            'status' => 'answered',
            'duration_seconds' => 42,
        ]);

        $this->postWebhook($body, $signature)
            ->assertOk()
            ->assertJsonPath('applied', true);

        $this->withBypass(function () use ($call): void {
            $fresh = VoiceCallLog::withoutGlobalScope('tenant')->findOrFail($call->id);

            $this->assertSame('answered', $fresh->status->value);
            $this->assertSame(42, $fresh->duration_seconds);
            $this->assertNotNull($fresh->answered_at);
        });
    }

    public function test_the_same_event_twice_is_ignored(): void
    {
        $call = $this->startedCall();

        [$body, $signature] = $this->signed([
            'event_id' => 'evt_completed',
            'provider_call_id' => $call->provider_call_id,
            'status' => 'completed',
        ]);

        $this->postWebhook($body, $signature)->assertOk()->assertJsonPath('applied', true);

        $this->postWebhook($body, $signature)
            ->assertOk()
            ->assertJsonPath('applied', false)
            ->assertJsonPath('message', 'Duplicate event ignored.');

        $this->withBypass(fn () => $this->assertSame(1, VoiceWebhookEvent::withoutGlobalScope('tenant')->count()));
    }

    public function test_an_unknown_call_is_acknowledged_but_ignored(): void
    {
        [$body, $signature] = $this->signed([
            'event_id' => 'evt_unknown',
            'provider_call_id' => 'does-not-exist',
            'status' => 'completed',
        ]);

        $this->postWebhook($body, $signature)
            ->assertStatus(202)
            ->assertJsonPath('applied', false);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $call = $this->startedCall();

        [$body, $signature] = $this->signed([
            'event_id' => 'evt_bad_status',
            'provider_call_id' => $call->provider_call_id,
            'status' => 'teleported',
        ]);

        $this->postWebhook($body, $signature)->assertStatus(422);
    }
}
