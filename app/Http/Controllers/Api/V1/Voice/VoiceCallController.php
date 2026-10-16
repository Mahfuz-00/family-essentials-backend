<?php

namespace App\Http\Controllers\Api\V1\Voice;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Resources\VoiceCallResource;
use App\Models\ServiceRequest;
use App\Models\VoiceCallLog;
use App\Voice\VoiceEscalationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceCallController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = VoiceCallLog::query()
            ->with(['request' => fn ($query) => $query->select('id', 'title')])
            ->where('user_id', $request->user()->getKey())
            ->latest('id')
            ->paginate(50);

        return VoiceCallResource::collection($logs)->response();
    }

    public function store(
        Request $request,
        ServiceRequest $serviceRequest,
        VoiceEscalationService $service,
    ): JsonResponse {
        $this->authorize('escalate', $serviceRequest);

        $call = $service->escalate($serviceRequest, manual: true);

        app(AuditRecorder::class)->record('voice_escalation.dispatched', [
            'request' => $serviceRequest->title,
            'status' => $call->status->value,
            'reason' => $call->reason,
        ], $call->status->value === 'skipped' ? 'warning' : 'info');

        return (new VoiceCallResource($call))->response()->setStatusCode(201);
    }

    /**
     * Today's escalation usage against the configured daily budget, so the UI
     * can show a real quota instead of a hardcoded one.
     */
    public function quota(Request $request): JsonResponse
    {
        $used = VoiceCallLog::query()
            ->where('user_id', $request->user()->getKey())
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotIn('status', ['skipped'])
            ->count();

        return response()->json([
            'used_today' => $used,
            'daily_budget' => (int) config('voice.daily_budget'),
            'max_attempts_per_request' => (int) config('voice.max_attempts_per_request'),
            'cooldown_minutes' => (int) config('voice.cooldown_minutes'),
        ]);
    }
}
