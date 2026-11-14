<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Platform\MarketingController as PlatformMarketingController;
use App\Http\Controllers\Controller;
use App\Models\MarketingAnnouncement;
use App\Models\MarketingLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingPublicController extends Controller
{
    /**
     * Submit an email to subscribe to updates or join waitlist from marketing website
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:64'],
            'campaign' => ['nullable', 'string', 'max:128'],
        ]);

        $lead = MarketingLead::updateOrCreate(
            ['email' => strtolower(trim($validated['email']))],
            [
                'name' => $validated['name'] ?? null,
                'source' => $validated['source'] ?? 'website',
                'campaign' => $validated['campaign'] ?? null,
                'status' => 'new',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Thank you for joining FairShare updates! We will be in touch.',
            'data' => [
                'id' => $lead->id,
                'email' => $lead->email,
            ],
        ], 201);
    }

    /**
     * Get active announcement banners to display in web/mobile headers
     */
    public function activeAnnouncements(): JsonResponse
    {
        $announcements = MarketingAnnouncement::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->latest('id')
            ->take(5)
            ->get();

        return response()->json([
            'data' => $announcements,
        ]);
    }

    /**
     * Get published blog posts for the public marketing website.
     */
    public function blogIndex(Request $request): JsonResponse
    {
        $posts = collect(PlatformMarketingController::getStoredBlogPosts())
            ->filter(fn (array $post) => (bool) ($post['is_published'] ?? true))
            ->when($request->filled('category') && $request->string('category') !== 'all', function ($collection) use ($request) {
                $cat = strtolower(trim($request->string('category')));

                return $collection->filter(fn (array $post) => strtolower($post['category'] ?? '') === $cat);
            })
            ->values()
            ->all();

        return response()->json([
            'data' => $posts,
        ]);
    }

    /**
     * Get a single published blog post by its URL slug.
     */
    public function blogShow(string $slug): JsonResponse
    {
        $post = collect(PlatformMarketingController::getStoredBlogPosts())
            ->first(fn (array $item) => ($item['slug'] ?? '') === $slug && (bool) ($item['is_published'] ?? true));

        if (! $post) {
            return response()->json([
                'message' => 'Blog article not found.',
            ], 404);
        }

        return response()->json([
            'data' => $post,
        ]);
    }
}
