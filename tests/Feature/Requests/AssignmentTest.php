<?php

namespace Tests\Feature\Requests;

use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use CreatesTenants;

    public function test_a_requester_can_assign_and_hand_over_a_request(): void
    {
        $tenant = $this->createTenant('Household A');
        $requester = $this->createMember($tenant, 'tenant_admin');
        $first = $this->createMember($tenant, 'member');
        $second = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($requester);

        $id = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Milk'])
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/v1/requests/{$id}/assign", ['assignee_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $first->id);

        $this->withToken($token)
            ->postJson("/api/v1/requests/{$id}/assign", ['assignee_id' => $second->id])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $second->id);

        $this->withBypass(function () use ($id): void {
            $this->assertDatabaseHas('assignments', ['request_id' => $id, 'action' => 'assigned']);
            $this->assertDatabaseHas('assignments', ['request_id' => $id, 'action' => 'reassigned']);
        });
    }

    public function test_a_member_who_is_not_the_requester_or_admin_cannot_assign(): void
    {
        $tenant = $this->createTenant('Household A');
        $requester = $this->createMember($tenant, 'tenant_admin');
        $assignee = $this->createMember($tenant, 'member');
        $bystander = $this->createMember($tenant, 'member');

        $id = $this->withToken($this->tokenFor($requester))
            ->postJson('/api/v1/requests', ['title' => 'Milk', 'assignee_id' => $assignee->id])
            ->json('data.id');

        $this->withToken($this->tokenFor($bystander))
            ->postJson("/api/v1/requests/{$id}/assign", ['assignee_id' => $bystander->id])
            ->assertForbidden();
    }

    public function test_assigning_to_someone_outside_the_household_is_rejected(): void
    {
        $tenant = $this->createTenant('Household A');
        $requester = $this->createMember($tenant, 'tenant_admin');

        $otherTenant = $this->createTenant('Household B');
        $outsider = $this->createMember($otherTenant, 'member');

        $token = $this->tokenFor($requester);

        $id = $this->withToken($token)
            ->postJson('/api/v1/requests', ['title' => 'Milk'])
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/v1/requests/{$id}/assign", ['assignee_id' => $outsider->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assignee_id']);
    }
}
