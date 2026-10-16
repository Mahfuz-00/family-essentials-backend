<?php

namespace App\Requests;

use App\Enums\AssignmentAction;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Assignment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Reminders\ReminderScheduler;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The single place that mutates a request's status, records the assignment /
 * handover history, and keeps its reminders in sync.
 */
class RequestWorkflow
{
    public function __construct(private readonly ReminderScheduler $reminders) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $requester, array $attributes): ServiceRequest
    {
        $request = new ServiceRequest;

        $request->forceFill([
            'item_id' => $attributes['item_id'] ?? null,
            'title' => $attributes['title'],
            'quantity' => $attributes['quantity'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'category' => $attributes['category'] ?? null,
            'priority' => $attributes['priority'] ?? RequestPriority::Normal,
            'store_name' => $attributes['store_name'] ?? null,
            'place_id' => $attributes['place_id'] ?? null,
            'due_at' => $attributes['due_at'] ?? null,
            'requested_by' => $requester->getKey(),
            'assignee_id' => $attributes['assignee_id'] ?? null,
            'status' => RequestStatus::Pending,
        ])->save();

        if ($request->assignee_id !== null) {
            $this->record($request, (int) $request->assignee_id, $requester, AssignmentAction::Assigned);
        }

        return $this->finish($request);
    }

    /**
     * Apply field edits (which may move the due date) and re-sync reminders.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(ServiceRequest $request, array $attributes): ServiceRequest
    {
        $request->fill($attributes)->save();

        return $this->finish($request);
    }

    public function assign(ServiceRequest $request, User $to, User $by): ServiceRequest
    {
        $this->assertOpen($request);

        $wasAssigned = $request->assignee_id !== null;
        $changed = (int) $request->assignee_id !== (int) $to->getKey();

        $request->forceFill(['assignee_id' => $to->getKey()])->save();

        if ($changed) {
            $this->record(
                $request,
                (int) $to->getKey(),
                $by,
                $wasAssigned ? AssignmentAction::Reassigned : AssignmentAction::Assigned,
            );
        }

        return $this->finish($request);
    }

    public function accept(ServiceRequest $request, User $by): ServiceRequest
    {
        $this->assertOpen($request);

        if ($request->assignee_id === null) {
            $request->forceFill(['assignee_id' => $by->getKey()])->save();
            $this->record($request, (int) $by->getKey(), $by, AssignmentAction::Assigned);
        }

        $request->forceFill(['status' => RequestStatus::Accepted])->save();
        $this->record($request, (int) $by->getKey(), $by, AssignmentAction::Accepted);

        return $this->finish($request);
    }

    public function decline(ServiceRequest $request, User $by, ?string $note = null): ServiceRequest
    {
        $this->assertOpen($request);

        $request->forceFill(['status' => RequestStatus::Declined])->save();
        $this->record($request, (int) $by->getKey(), $by, AssignmentAction::Declined, $note);

        return $this->finish($request);
    }

    public function start(ServiceRequest $request, User $by): ServiceRequest
    {
        $this->assertOpen($request);

        $request->forceFill(['status' => RequestStatus::InProgress])->save();
        $this->record($request, (int) $by->getKey(), $by, AssignmentAction::Started);

        return $this->finish($request);
    }

    public function complete(ServiceRequest $request, User $by): ServiceRequest
    {
        $this->assertOpen($request);

        $request->forceFill([
            'status' => RequestStatus::Completed,
            'completed_at' => Carbon::now(),
        ])->save();

        $this->record($request, (int) ($request->assignee_id ?? $by->getKey()), $by, AssignmentAction::Completed);

        return $this->finish($request);
    }

    public function cancel(ServiceRequest $request, User $by): ServiceRequest
    {
        $this->assertOpen($request);

        $request->forceFill([
            'status' => RequestStatus::Cancelled,
            'cancelled_at' => Carbon::now(),
        ])->save();

        $this->record($request, (int) ($request->assignee_id ?? $by->getKey()), $by, AssignmentAction::Cancelled);

        return $this->finish($request);
    }

    public function markUnavailable(ServiceRequest $request, User $by, ?string $note = null): ServiceRequest
    {
        $this->assertOpen($request);

        $request->forceFill(['status' => RequestStatus::Unavailable])->save();
        $this->record($request, (int) ($request->assignee_id ?? $by->getKey()), $by, AssignmentAction::Unavailable, $note);

        return $this->finish($request);
    }

    public function snooze(ServiceRequest $request, Carbon $until): ServiceRequest
    {
        $this->assertOpen($request);

        $request->forceFill(['snooze_until' => $until])->save();

        return $this->finish($request);
    }

    public function defer(ServiceRequest $request, Carbon $dueAt): ServiceRequest
    {
        $this->assertOpen($request);

        $request->forceFill(['due_at' => $dueAt, 'snooze_until' => null])->save();

        return $this->finish($request);
    }

    /**
     * Refresh the model and re-sync its reminders (Phase 4).
     */
    private function finish(ServiceRequest $request): ServiceRequest
    {
        $fresh = $request->refresh();

        $this->reminders->sync($fresh);

        return $fresh;
    }

    private function assertOpen(ServiceRequest $request): void
    {
        if ($request->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'This request is already closed.',
            ]);
        }
    }

    private function record(
        ServiceRequest $request,
        int $assignedTo,
        ?User $by,
        AssignmentAction $action,
        ?string $note = null,
    ): void {
        Assignment::create([
            'request_id' => $request->getKey(),
            'assigned_to' => $assignedTo,
            'assigned_by' => $by?->getKey(),
            'action' => $action,
            'note' => $note,
        ]);
    }
}
