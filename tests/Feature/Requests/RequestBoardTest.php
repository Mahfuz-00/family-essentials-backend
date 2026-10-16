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

    public function test_an_undated_unassigned_request_lands_in_the_backlog(): void
    {
        $tenant = $this->createTenant('Household A');
        $me = $this->createMember($tenant, 'tenant_admin');

        // The case that used to match no column at all, so a successful create
        // looked like nothing had happened.
        $this->createRequest($tenant, $me, ['title' => 'Bin bags']);

        $this->withToken($this->tokenFor($me))
            ->getJson('/api/v1/requests/board')
            ->assertOk()
            ->assertJsonPath('counts.backlog', 1)
            ->assertJsonPath('counts.open', 1)
            ->assertJsonPath('backlog.0.title', 'Bin bags');
    }

    public function test_a_future_dated_request_assigned_to_someone_else_is_backlog(): void
    {
        $tenant = $this->createTenant('Household A');
        $me = $this->createMember($tenant, 'tenant_admin');
        $other = $this->createMember($tenant, 'member');

        $this->createRequest($tenant, $me, [
            'title' => 'Next week',
            'due_at' => now()->addWeek(),
            'assignee_id' => $other->id,
        ]);

        $this->withToken($this->tokenFor($me))
            ->getJson('/api/v1/requests/board')
            ->assertOk()
            ->assertJsonPath('counts.backlog', 1)
            ->assertJsonPath('counts.overdue', 0)
            ->assertJsonPath('counts.due_today', 0);
    }

    public function test_the_buckets_cover_every_open_request(): void
    {
        $tenant = $this->createTenant('Household A');
        $me = $this->createMember($tenant, 'tenant_admin');
        $other = $this->createMember($tenant, 'member');

        $this->createRequest($tenant, $me, ['title' => 'Overdue', 'due_at' => now()->subDay(), 'assignee_id' => $me->id]);
        $this->createRequest($tenant, $me, ['title' => 'Today', 'due_at' => now()->endOfDay()]);
        $this->createRequest($tenant, $me, ['title' => 'Mine later', 'due_at' => now()->addWeek(), 'assignee_id' => $me->id]);
        $this->createRequest($tenant, $me, ['title' => 'Undated']);
        $this->createRequest($tenant, $me, ['title' => 'Someone else', 'assignee_id' => $other->id]);

        $board = $this->withToken($this->tokenFor($me))
            ->getJson('/api/v1/requests/board')
            ->assertOk()
            ->json();

        // Every open request is visible in at least one bucket: nothing is lost.
        $visible = collect([
            ...$board['overdue'],
            ...$board['due_today'],
            ...$board['assigned_to_me'],
            ...$board['backlog'],
        ])->pluck('title')->unique()->sort()->values()->all();

        $this->assertSame(
            ['Mine later', 'Overdue', 'Someone else', 'Today', 'Undated'],
            $visible,
        );

        // `open` counts distinct requests, so it is not the (overlapping) sum.
        $this->assertSame(5, $board['counts']['open']);

        // The overdue item is assigned to me too, so it is in two buckets.
        $assigned = collect($board['assigned_to_me'])->pluck('title')->sort()->values()->all();
        $this->assertSame(['Mine later', 'Overdue'], $assigned);
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
