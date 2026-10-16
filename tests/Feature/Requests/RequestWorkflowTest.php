<?php

namespace Tests\Feature\Requests;

use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class RequestWorkflowTest extends TestCase
{
    use CreatesTenants;

    public function test_an_assignee_can_accept_start_and_complete_a_request(): void
    {
        $tenant = $this->createTenant('Household A');
        $requester = $this->createMember($tenant, 'tenant_admin');
        $assignee = $this->createMember($tenant, 'member');

        $created = $this->withToken($this->tokenFor($requester))
            ->postJson('/api/v1/requests', ['title' => 'Buy milk', 'assignee_id' => $assignee->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $id = $created->json('data.id');
        $token = $this->tokenFor($assignee);

        $this->withToken($token)->postJson("/api/v1/requests/{$id}/accept")
            ->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->withToken($token)->postJson("/api/v1/requests/{$id}/start")
            ->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->withToken($token)->postJson("/api/v1/requests/{$id}/complete")
            ->assertOk()->assertJsonPath('data.status', 'completed');

        // assigned + accepted + started + completed
        $this->withBypass(fn () => $this->assertDatabaseCount('assignments', 4));
    }

    public function test_a_completed_request_cannot_be_transitioned_again(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $id = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Bread'])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)->postJson("/api/v1/requests/{$id}/accept")->assertOk();
        $this->withToken($token)->postJson("/api/v1/requests/{$id}/complete")->assertOk();

        $this->withToken($token)->postJson("/api/v1/requests/{$id}/start")
            ->assertStatus(422);
    }

    public function test_a_non_assignee_cannot_accept_a_request(): void
    {
        $tenant = $this->createTenant('Household A');
        $requester = $this->createMember($tenant, 'tenant_admin');
        $assignee = $this->createMember($tenant, 'member');
        $bystander = $this->createMember($tenant, 'member');

        $id = $this->withToken($this->tokenFor($requester))
            ->postJson('/api/v1/requests', ['title' => 'Milk', 'assignee_id' => $assignee->id])
            ->json('data.id');

        $this->withToken($this->tokenFor($bystander))
            ->postJson("/api/v1/requests/{$id}/accept")
            ->assertForbidden();
    }

    public function test_a_requester_can_cancel_a_request(): void
    {
        $tenant = $this->createTenant('Household A');
        $requester = $this->createMember($tenant, 'tenant_admin');
        $token = $this->tokenFor($requester);

        $id = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Milk'])
            ->json('data.id');

        $this->withToken($token)->postJson("/api/v1/requests/{$id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_an_assignee_can_mark_an_item_unavailable_and_decline(): void
    {
        $tenant = $this->createTenant('Household A');
        $requester = $this->createMember($tenant, 'tenant_admin');
        $assignee = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($requester);

        $unavailableId = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Rare item', 'assignee_id' => $assignee->id])
            ->json('data.id');

        $assigneeToken = $this->tokenFor($assignee);

        $this->withToken($assigneeToken)
            ->postJson("/api/v1/requests/{$unavailableId}/unavailable", ['note' => 'Out of stock'])
            ->assertOk()->assertJsonPath('data.status', 'unavailable');

        $declinedId = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Not mine', 'assignee_id' => $assignee->id])
            ->json('data.id');

        $this->withToken($assigneeToken)
            ->postJson("/api/v1/requests/{$declinedId}/decline", ['note' => 'Too busy'])
            ->assertOk()->assertJsonPath('data.status', 'declined');
    }

    public function test_a_request_can_be_snoozed(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $id = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Milk'])
            ->json('data.id');

        $until = now()->addHours(2)->startOfSecond();

        $this->withToken($token)
            ->postJson("/api/v1/requests/{$id}/snooze", ['until' => $until->toDateTimeString()])
            ->assertOk()
            ->assertJsonPath('data.snooze_until', $until->toISOString());
    }

    public function test_the_request_history_records_each_transition(): void
    {
        $tenant = $this->createTenant('Household A');
        $member = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($member);

        $id = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Milk'])
            ->json('data.id');

        $this->withToken($token)->postJson("/api/v1/requests/{$id}/accept")->assertOk();
        $this->withToken($token)->postJson("/api/v1/requests/{$id}/complete")->assertOk();

        $this->withToken($token)
            ->getJson("/api/v1/requests/{$id}")
            ->assertOk()
            ->assertJsonPath('data.history.0.action', 'completed')
            ->assertJsonPath('data.history.1.action', 'accepted');
    }
}
