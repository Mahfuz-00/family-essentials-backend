<?php

namespace Tests\Integration\Voice;

use App\Models\VoiceCallLog;
use App\Models\VoiceWebhookEvent;
use App\Voice\VoiceEscalationService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class VoiceIsolationTest extends TestCase
{
    use CreatesTenants;

    public function test_a_tenant_only_sees_its_own_call_log(): void
    {
        $tenantA = $this->createTenant('Household A');
        $memberA = $this->optInForVoice($this->createMember($tenantA, 'member'));
        $this->asTenant($tenantA->id, fn () => app(VoiceEscalationService::class)
            ->escalate($this->createEscalationCandidate($tenantA, $memberA)));

        $tenantB = $this->createTenant('Household B');
        $memberB = $this->optInForVoice($this->createMember($tenantB, 'member'));
        $this->asTenant($tenantB->id, fn () => app(VoiceEscalationService::class)
            ->escalate($this->createEscalationCandidate($tenantB, $memberB)));

        $this->withToken($this->tokenFor($memberA))
            ->getJson('/api/v1/voice-calls')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withBypass(fn () => $this->assertSame(2, VoiceCallLog::withoutGlobalScope('tenant')->count()));
    }

    public function test_a_member_cannot_manually_escalate_another_tenants_request(): void
    {
        $tenantA = $this->createTenant('Household A');
        $adminA = $this->createMember($tenantA, 'tenant_admin');

        $tenantB = $this->createTenant('Household B');
        $memberB = $this->optInForVoice($this->createMember($tenantB, 'member'));
        $foreign = $this->createEscalationCandidate($tenantB, $memberB);

        $this->withToken($this->tokenFor($adminA))
            ->postJson("/api/v1/requests/{$foreign->id}/voice-escalation")
            ->assertNotFound();
    }

    public function test_a_regular_member_cannot_trigger_a_manual_escalation(): void
    {
        $tenant = $this->createTenant('Household A');
        $requester = $this->createMember($tenant, 'tenant_admin');
        $assignee = $this->optInForVoice($this->createMember($tenant, 'member'));
        $bystander = $this->createMember($tenant, 'member');

        $request = $this->createEscalationCandidate($tenant, $assignee);

        $this->withToken($this->tokenFor($bystander))
            ->postJson("/api/v1/requests/{$request->id}/voice-escalation")
            ->assertForbidden();

        // The requester (an admin here) may trigger it manually.
        $this->withToken($this->tokenFor($requester))
            ->postJson("/api/v1/requests/{$request->id}/voice-escalation")
            ->assertCreated()
            ->assertJsonPath('data.reason', 'manual');
    }

    public function test_rls_denies_the_voice_tables_without_a_tenant_context(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->optInForVoice($this->createMember($tenant, 'member'));
        $this->asTenant($tenant->id, fn () => app(VoiceEscalationService::class)
            ->escalate($this->createEscalationCandidate($tenant, $member)));

        $this->withBypass(fn () => VoiceWebhookEvent::create([
            'tenant_id' => $tenant->id,
            'provider' => 'fake',
            'provider_event_id' => 'evt-rls',
            'processed_at' => now(),
        ]));

        $connection = DB::connection('pgsql');
        $connection->statement("select set_config('app.current_tenant', '', false)");
        $connection->statement("select set_config('app.bypass_rls', 'off', false)");

        $this->assertSame(0, $connection->table('voice_call_logs')->count());
        $this->assertSame(0, $connection->table('voice_webhook_events')->count());
    }
}
