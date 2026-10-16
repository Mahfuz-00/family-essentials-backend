<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationLogResource;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $logs = NotificationLog::query()
            ->with(['request' => fn ($query) => $query->select('id', 'title')])
            ->where(function ($query) use ($userId): void {
                $query->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->latest('id')
            ->paginate(50);

        return NotificationLogResource::collection($logs)->response();
    }
}
