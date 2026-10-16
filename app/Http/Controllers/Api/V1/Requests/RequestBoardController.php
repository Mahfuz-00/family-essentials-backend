<?php

namespace App\Http\Controllers\Api\V1\Requests;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestBoardController extends Controller
{
    /**
     * Grouped board payload backing the web Kanban and the Flutter Today list.
     *
     * The buckets must cover every open request between them. They did not: an
     * item created without a due date and without an assignee matched none of the
     * four columns, so a successful create looked like nothing had happened.
     * `backlog` exists to catch exactly that case, and `counts.open` is the figure
     * the metric strip reconciles against.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $overdue = $this->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->get();

        $dueToday = $this->open()
            ->whereDate('due_at', now()->toDateString())
            ->orderBy('due_at')
            ->get();

        $assignedToMe = $this->open()
            ->where('assignee_id', $userId)
            ->orderBy('due_at')
            ->get();

        // Open, not overdue, not due today, and not already in "assigned to me".
        $backlog = $this->open()
            ->where(function (Builder $query): void {
                $query->whereNull('due_at')->orWhere('due_at', '>', now()->endOfDay());
            })
            ->where(function (Builder $query) use ($userId): void {
                $query->whereNull('assignee_id')->orWhere('assignee_id', '!=', $userId);
            })
            ->orderByRaw('due_at asc nulls first')
            ->orderByDesc('id')
            ->get();

        $completedToday = ServiceRequest::query()->with($this->relations())
            ->where('status', 'completed')
            ->whereDate('completed_at', now()->toDateString())
            ->orderByDesc('completed_at')
            ->get();

        return response()->json([
            'counts' => [
                // Every request ever, for the household total.
                'total' => ServiceRequest::query()->count(),
                // Distinct open requests. Not the sum of the buckets: they overlap
                // (an overdue item assigned to me is in two of them), so the sum
                // would double-count.
                'open' => ServiceRequest::query()
                    ->whereIn('status', ['pending', 'accepted', 'in_progress'])
                    ->count(),
                'overdue' => $overdue->count(),
                'due_today' => $dueToday->count(),
                'assigned_to_me' => $assignedToMe->count(),
                'backlog' => $backlog->count(),
                'completed_today' => $completedToday->count(),
            ],
            'overdue' => ServiceRequestResource::collection($overdue),
            'due_today' => ServiceRequestResource::collection($dueToday),
            'assigned_to_me' => ServiceRequestResource::collection($assignedToMe),
            'backlog' => ServiceRequestResource::collection($backlog),
            'completed_today' => ServiceRequestResource::collection($completedToday),
        ]);
    }

    /**
     * @return Builder<ServiceRequest>
     */
    private function open(): Builder
    {
        return ServiceRequest::query()
            ->with($this->relations())
            ->whereIn('status', ['pending', 'accepted', 'in_progress']);
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return ['item', 'place', 'requester', 'assignee'];
    }
}
