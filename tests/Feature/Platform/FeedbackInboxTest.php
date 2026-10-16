<?php

namespace Tests\Feature\Platform;

use App\Models\FeedbackReport;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class FeedbackInboxTest extends TestCase
{
    use CreatesTenants;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function report(int $tenantId, int $userId, array $attributes = []): FeedbackReport
    {
        return $this->withBypass(fn (): FeedbackReport => FeedbackReport::withoutGlobalScope('tenant')->create(
            array_merge([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => 'bug',
                'priority' => 'normal',
                'subject' => 'Something is broken',
                'body' => 'Enough detail to be a valid report body.',
            ], $attributes)
        ));
    }

    public function test_the_inbox_gathers_reports_from_every_household(): void
    {
        $one = $this->createTenant('Household A');
        $two = $this->createTenant('Household B');
        $platform = $this->createPlatformAdmin();

        $this->report($one->id, $this->createMember($one, 'member')->id, ['subject' => 'From A']);
        $this->report($two->id, $this->createMember($two, 'member')->id, ['subject' => 'From B']);

        $payload = $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/feedback')
            ->assertOk()
            ->json();

        $this->assertCount(2, $payload['data']);
        $this->assertSame(2, $payload['summary']['open']);

        $tenants = collect($payload['data'])->pluck('tenant.name')->all();
        $this->assertContains('Household A', $tenants);
        $this->assertContains('Household B', $tenants);

        // The inbox carries who reported it, including how to reach them.
        $this->assertNotNull($payload['data'][0]['reporter']['email']);
        $this->assertArrayHasKey('age_hours', $payload['data'][0]);
    }

    public function test_the_inbox_summarises_what_is_waiting(): void
    {
        $tenant = $this->createTenant('Household A');
        $reporter = $this->createMember($tenant, 'member');
        $platform = $this->createPlatformAdmin();

        $this->report($tenant->id, $reporter->id, ['priority' => 'critical']);
        $this->report($tenant->id, $reporter->id, ['type' => 'feature_request']);
        $this->report($tenant->id, $reporter->id, ['status' => 'resolved', 'resolved_at' => now()]);

        $summary = $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/feedback')
            ->assertOk()
            ->json('summary');

        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['open']);
        $this->assertSame(1, $summary['critical_open']);
        // Only the two still at `new` count as unseen by an operator.
        $this->assertSame(2, $summary['unseen']);
        $this->assertSame(2, $summary['by_type']['bug']);
        $this->assertSame(1, $summary['by_type']['feature_request']);
        $this->assertNotNull($summary['oldest_open_at']);
    }

    public function test_the_inbox_can_be_narrowed(): void
    {
        $one = $this->createTenant('Household A');
        $two = $this->createTenant('Household B');
        $platform = $this->createPlatformAdmin();

        $this->report($one->id, $this->createMember($one, 'member')->id, ['type' => 'bug']);
        $this->report($two->id, $this->createMember($two, 'member')->id, ['type' => 'feature_request']);
        $this->report($two->id, $this->createMember($two, 'member')->id, ['type' => 'bug', 'status' => 'resolved']);

        $token = $this->tokenFor($platform);

        $this->withToken($token)->getJson('/api/v1/platform/feedback?type=bug')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->withToken($token)->getJson('/api/v1/platform/feedback?open_only=1')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->withToken($token)->getJson("/api/v1/platform/feedback?tenant_id={$two->id}")
            ->assertOk()->assertJsonCount(2, 'data');

        $this->withToken($token)->getJson('/api/v1/platform/feedback?status=resolved')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_triaging_a_report_is_audited_and_stamps_the_resolution(): void
    {
        $tenant = $this->createTenant('Household A');
        $reporter = $this->createMember($tenant, 'member');
        $platform = $this->createPlatformAdmin();
        $report = $this->report($tenant->id, $reporter->id);
        $token = $this->tokenFor($platform);

        $this->withToken($token)
            ->patchJson("/api/v1/platform/feedback/{$report->id}", [
                'status' => 'in_progress',
                'priority' => 'high',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.resolved_at', null);

        $this->withToken($token)
            ->patchJson("/api/v1/platform/feedback/{$report->id}", [
                'status' => 'resolved',
                'resolution_note' => 'Fixed in 1.4.1 — the list now survives a reload.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolver.name', $platform->name);

        $this->withBypass(function () use ($report, $platform): void {
            $stored = FeedbackReport::withoutGlobalScope('tenant')->findOrFail($report->id);

            $this->assertNotNull($stored->resolved_at);
            $this->assertSame($platform->id, $stored->resolved_by);
        });

        $this->assertDatabaseHas('audit_events', [
            'action' => 'ops.feedback.triaged',
            'level' => 'info',
        ]);
    }

    public function test_reopening_a_report_clears_the_resolution_the_household_was_shown(): void
    {
        $tenant = $this->createTenant('Household A');
        $reporter = $this->createMember($tenant, 'member');
        $platform = $this->createPlatformAdmin();
        $report = $this->report($tenant->id, $reporter->id);
        $token = $this->tokenFor($platform);

        $this->withToken($token)
            ->patchJson("/api/v1/platform/feedback/{$report->id}", [
                'status' => 'resolved',
                'resolution_note' => 'Believed fixed.',
            ])
            ->assertOk();

        // The household saw "resolved" — reopening must not leave that claim behind.
        $this->withToken($token)
            ->patchJson("/api/v1/platform/feedback/{$report->id}", ['status' => 'triaged'])
            ->assertOk()
            ->assertJsonPath('data.resolved_at', null);

        $this->withBypass(function () use ($report): void {
            $stored = FeedbackReport::withoutGlobalScope('tenant')->findOrFail($report->id);

            $this->assertNull($stored->resolved_at);
            $this->assertNull($stored->resolved_by);
        });
    }

    public function test_a_triage_change_must_use_known_values(): void
    {
        $tenant = $this->createTenant('Household A');
        $reporter = $this->createMember($tenant, 'member');
        $platform = $this->createPlatformAdmin();
        $report = $this->report($tenant->id, $reporter->id);

        $this->withToken($this->tokenFor($platform))
            ->patchJson("/api/v1/platform/feedback/{$report->id}", ['status' => 'ignored'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_the_inbox_is_closed_to_households(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $report = $this->report($tenant->id, $admin->id);

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/platform/feedback')
            ->assertForbidden();

        $this->withToken($this->tokenFor($admin))
            ->patchJson("/api/v1/platform/feedback/{$report->id}", ['status' => 'resolved'])
            ->assertForbidden();
    }
}
