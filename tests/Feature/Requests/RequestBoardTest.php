<?php

namespace Tests\Feature\Requests;

use App\Enums\RequestStatus;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class RequestBoardTest extends TestCase
{
    use CreatesTenants;

    public function test_the_board_groups_requests_into_the_four_columns(): void
    {
        $tenant = $this->createTenant('Household A');
        $me = $this->createMember($tenant, 'tenant_admin');
        $token = $this->tokenFor($me);

        $this->createRequest($tenant, $me, [
            'title' => 'Overdue milk',
            'due_at' => now()->subDay(),
            'assignee_id' => $me->id,
        ]);

        $this->createRequest($tenant, $me, [
            'title' => 'Later today',
            'due_at' => now()->endOfDay(),
        ]);

        $completed = $this->createRequest($tenant, $me, [
            'title' => 'Already done',
            'status' => RequestStatus::Completed,
            'completed_at' => now(),
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/requests/board')->assertOk();

        $response->assertJsonPath('counts.overdue', 1)
            ->assertJsonPath('counts.due_today', 1)
            ->assertJsonPath('counts.assigned_to_me', 1)
            ->assertJsonPath('counts.completed_today', 1)
            ->assertJsonPath('overdue.0.title', 'Overdue milk')
            ->assertJsonPath('due_today.0.title', 'Later today')
            ->assertJsonPath('assigned_to_me.0.title', 'Overdue milk')
            ->assertJsonPath('completed_today.0.title', 'Already done');

        $this->assertNotNull($completed);
    }

    public function test_the_board_ignores_closed_requests_in_the_open_columns(): void
    {
        $tenant = $this->createTenant('Household A');
        $me = $this->createMember($tenant, 'tenant_admin');

        $this->createRequest($tenant, $me, [
            'title' => 'Cancelled overdue',
            'due_at' => now()->subDay(),
            'status' => RequestStatus::Cancelled,
        ]);

        $this->withToken($this->tokenFor($me))
            ->getJson('/api/v1/requests/board')
            ->assertOk()
            ->assertJsonPath('counts.overdue', 0)
            ->assertJsonPath('counts.completed_today', 0);
    }

    public function test_requests_can_be_filtered_by_assignee_and_status(): void
    {
        $tenant = $this->createTenant('Household A');
        $me = $this->createMember($tenant, 'tenant_admin');
        $other = $this->createMember($tenant, 'member');
        $token = $this->tokenFor($me);

        $this->createRequest($tenant, $me, ['title' => 'Mine', 'assignee_id' => $me->id]);
        $this->createRequest($tenant, $me, ['title' => 'Theirs', 'assignee_id' => $other->id]);
        $this->createRequest($tenant, $me, ['title' => 'Nobody']);

        $this->withToken($token)->getJson('/api/v1/requests?assignee=me')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Mine');

        $this->withToken($token)->getJson('/api/v1/requests?assignee=unassigned')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Nobody');
    }
}
