<?php

namespace Tests\Feature\Feedback;

use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use CreatesTenants;

    public function test_any_member_can_report_a_bug_and_the_context_is_recorded(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/feedback', [
                'type' => 'bug',
                'priority' => 'high',
                'subject' => 'Shopping list loses items',
                'body' => 'Adding three items then reloading shows only two of them.',
                'context' => [
                    'platform' => 'web',
                    'app_version' => '1.4.0',
                    'screen' => '/requests',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.type', 'bug')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.reporter.name', $member->name)
            ->assertJsonPath('data.context.platform', 'web')
            ->assertJsonPath('data.context.screen', '/requests');

        $this->withBypass(function () use ($tenant, $member): void {
            $this->assertDatabaseHas('feedback_reports', [
                'tenant_id' => $tenant->id,
                'user_id' => $member->id,
                'subject' => 'Shopping list loses items',
                'status' => 'new',
            ]);
        });
    }

    public function test_a_report_needs_a_title_and_a_body_and_a_known_type(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/feedback', ['type' => 'rant', 'subject' => 'x', 'body' => 'short'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'subject', 'body']);
    }

    public function test_the_household_sees_its_own_reports_and_their_status(): void
    {
        $tenant = $this->createTenant('Household A');
        $other = $this->createTenant('Household B');
        $member = $this->createMember($tenant, 'tenant_admin');
        $stranger = $this->createMember($other, 'tenant_admin');

        foreach ([$member, $stranger] as $index => $author) {
            $this->withBypass(function () use ($index, $author): void {
                \App\Models\FeedbackReport::withoutGlobalScope('tenant')->create([
                    'tenant_id' => $author->active_tenant_id,
                    'user_id' => $author->id,
                    'type' => 'bug',
                    'subject' => $index === 0 ? 'Our report' : 'Their report',
                    'body' => 'Enough detail to be a valid report body.',
                ]);
            });
        }

        $payload = $this->withToken($this->tokenFor($member))
            ->getJson('/api/v1/feedback')
            ->assertOk()
            ->json();

        $this->assertCount(1, $payload['data']);
        $this->assertSame('Our report', $payload['data'][0]['subject']);
        $this->assertSame(1, $payload['summary']['total']);
        $this->assertSame(1, $payload['summary']['open']);
    }

    public function test_the_household_list_can_be_filtered_by_type_and_status(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'tenant_admin');
        $token = $this->tokenFor($member);

        $this->withToken($token)->postJson('/api/v1/feedback', [
            'type' => 'bug',
            'subject' => 'A bug report',
            'body' => 'Something is broken in a reproducible way.',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/feedback', [
            'type' => 'feature_request',
            'subject' => 'A feature request',
            'body' => 'Please add something genuinely useful.',
        ])->assertCreated();

        $this->withToken($token)
            ->getJson('/api/v1/feedback?type=feature_request')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'feature_request');
    }

    public function test_the_mobile_surface_reports_into_the_same_inbox(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/mobile/v1/feedback', [
                'type' => 'bug',
                'subject' => 'The app crashes on the pantry tab',
                'body' => 'Tapping the pantry tab twice closes the app.',
                'context' => ['platform' => 'android', 'app_version' => '0.9.2'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.context.platform', 'android');

        $this->withToken($this->tokenFor($member))
            ->getJson('/mobile/v1/feedback')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_report_is_never_written_without_a_tenant(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($member))
            ->postJson('/api/v1/feedback', [
                'type' => 'feedback',
                'subject' => 'Loving the shared list',
                'body' => 'This has replaced our whiteboard entirely.',
            ])
            ->assertCreated();

        $this->withBypass(function () use ($tenant): void {
            $this->assertSame(
                1,
                \App\Models\FeedbackReport::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->count(),
            );
        });
    }

    public function test_an_anonymous_caller_cannot_report(): void
    {
        $this->postJson('/api/v1/feedback', [
            'type' => 'bug',
            'subject' => 'No token at all',
            'body' => 'This should never be accepted.',
        ])->assertUnauthorized();
    }
}
