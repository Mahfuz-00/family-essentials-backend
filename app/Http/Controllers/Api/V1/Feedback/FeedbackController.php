<?php

namespace App\Http\Controllers\Api\V1\Feedback;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\StoreFeedbackRequest;
use App\Http\Resources\FeedbackReportResource;
use App\Models\FeedbackReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A household's own feedback.
 *
 * Shared by both client surfaces: a bug report from the dashboard and one from
 * the app land in the same inbox. Any member may report — this is a channel for
 * the household, not an administrative action.
 */
class FeedbackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = FeedbackReport::query()
            ->with('reporter:id,name')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => FeedbackReportResource::collection($reports),
            'summary' => [
                'total' => FeedbackReport::query()->count(),
                'open' => FeedbackReport::query()->open()->count(),
                // The household sees whether anyone has picked it up yet.
                'last_sent_at' => FeedbackReport::query()->max('created_at'),
            ],
        ]);
    }

    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        $report = FeedbackReport::create([
            'user_id' => $request->user()->getKey(),
            'type' => $request->string('type'),
            'priority' => $request->input('priority', 'normal'),
            // Explicit rather than left to the column default: the in-memory model
            // would otherwise carry null until it was read back.
            'status' => 'new',
            'subject' => $request->string('subject'),
            'body' => $request->string('body'),
            'context' => $this->context($request),
        ]);

        return (new FeedbackReportResource($report->load('reporter:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * What the client said, plus what the request itself reveals. Enough to
     * reproduce a bug without asking the household for their environment.
     *
     * @return array<string, string>
     */
    private function context(Request $request): array
    {
        $client = (array) $request->input('context', []);

        return array_filter([
            'platform' => $client['platform'] ?? null,
            'app_version' => $client['app_version'] ?? null,
            'screen' => $client['screen'] ?? null,
            'user_agent' => Str::limit((string) $request->userAgent(), 300, ''),
            'app_env' => app()->environment(),
            'source' => $request->headers->get('X-Client-Surface'),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }
}
