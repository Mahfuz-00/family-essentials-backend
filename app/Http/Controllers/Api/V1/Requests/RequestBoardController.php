<?php

namespace App\Http\Controllers\Api\V1\Requests;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestBoardController extends Controller
{
    /**
     * Grouped board payload backing the web Kanban and the Flutter Today list.
     */
    public function index(Request $request): JsonResponse
    {
        $open = ['pending', 'accepted', 'in_progress'];
        $userId = $request->user()->getKey();

        $overdue = ServiceRequest::query()->with(['item', 'place', 'requester', 'assignee'])
            ->whereIn('status', $open)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->get();

        $dueToday = ServiceRequest::query()->with(['item', 'place', 'requester', 'assignee'])
            ->whereIn('status', $open)
            ->whereDate('due_at', now()->toDateString())
            ->orderBy('due_at')
            ->get();

        $assignedToMe = ServiceRequest::query()->with(['item', 'place', 'requester', 'assignee'])
            ->whereIn('status', $open)
            ->where('assignee_id', $userId)
            ->orderBy('due_at')
            ->get();

        $completedToday = ServiceRequest::query()->with(['item', 'place', 'requester', 'assignee'])
            ->where('status', 'completed')
            ->whereDate('completed_at', now()->toDateString())
            ->orderByDesc('completed_at')
            ->get();

        return response()->json([
            'counts' => [
                'total' => ServiceRequest::query()->count(),
                'overdue' => $overdue->count(),
                'due_today' => $dueToday->count(),
                'assigned_to_me' => $assignedToMe->count(),
                'completed_today' => $completedToday->count(),
            ],
            'overdue' => ServiceRequestResource::collection($overdue),
            'due_today' => ServiceRequestResource::collection($dueToday),
            'assigned_to_me' => ServiceRequestResource::collection($assignedToMe),
            'completed_today' => ServiceRequestResource::collection($completedToday),
        ]);
    }
}
