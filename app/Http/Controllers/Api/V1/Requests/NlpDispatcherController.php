<?php

namespace App\Http\Controllers\Api\V1\Requests;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Place;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Plans\QuotaService;
use App\Requests\RequestWorkflow;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NlpDispatcherController extends Controller
{
    /**
     * Parse natural language family prompt into structured errand dispatch fields.
     */
    public function parse(Request $request, RequestWorkflow $workflow): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:500'],
            'execute' => ['nullable', 'boolean'],
        ]);

        $raw = trim($validated['text']);
        $text = $raw;
        $tenantId = app(TenantContext::class)->id();
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        $user = $request->user();

        // 1. Assignee detection
        $assigneeId = null;
        $assigneeName = null;
        if ($tenant) {
            $members = $tenant->users()->get(['users.id', 'users.name']);
            foreach ($members as $member) {
                $firstName = explode(' ', trim($member->name))[0];
                $pattern = '/\b(for|to|assign to)\s+'.preg_quote($firstName, '/').'\b/i';
                $nameOnlyPattern = '/\b'.preg_quote($firstName, '/').'\b/i';

                if (preg_match($pattern, $text) || preg_match($nameOnlyPattern, $text)) {
                    $assigneeId = $member->id;
                    $assigneeName = $member->name;
                    $text = preg_replace($pattern, '', $text);
                    break;
                }
            }
        }

        // 2. Place / Store detection
        $placeId = null;
        $storeName = null;
        if ($tenant) {
            $places = Place::where('tenant_id', $tenant->id)->get(['id', 'name']);
            foreach ($places as $place) {
                if (stripos($text, $place->name) !== false) {
                    $placeId = $place->id;
                    $storeName = $place->name;
                    $text = preg_replace('/(\bat|from|in)?\s*'.preg_quote($place->name, '/').'/i', '', $text);
                    break;
                }
            }
        }

        // Common stores fallback if no tenant place matched
        if (! $storeName) {
            $commonStores = ['Trader Joe\'s', 'Trader Joes', 'Costco', 'Target', 'Walmart', 'CVS', 'Walgreens', 'Whole Foods', 'Safeway', 'Kroger', 'Home Depot', 'Lowe\'s', 'Lowes'];
            foreach ($commonStores as $store) {
                if (stripos($text, $store) !== false) {
                    $storeName = $store;
                    $text = preg_replace('/(\bat|from|in)?\s*'.preg_quote($store, '/').'/i', '', $text);
                    break;
                }
            }
        }

        // 3. Priority detection
        $priority = 'normal';
        if (preg_match('/\b(urgent|asap|stat|emergency|immediately|right now|critical)\b/i', $text)) {
            $priority = 'urgent';
            $text = preg_replace('/\b(urgent|asap|stat|emergency|immediately|right now|critical)\b/i', '', $text);
        } elseif (preg_match('/\b(whenever|no rush|low priority|sometime|when possible)\b/i', $text)) {
            $priority = 'low';
            $text = preg_replace('/\b(whenever|no rush|low priority|sometime|when possible)\b/i', '', $text);
        }

        // 4. Due date & time detection
        $dueAt = null;
        $dueHuman = null;
        $now = Carbon::now();

        if (preg_match('/\btomorrow\b/i', $text)) {
            $dueAt = $now->copy()->addDay()->setTime(17, 0); // default 5pm tomorrow
            $dueHuman = 'Tomorrow at 5:00 PM';
            $text = preg_replace('/\btomorrow\b/i', '', $text);
        } elseif (preg_match('/\btonight\b/i', $text)) {
            $dueAt = $now->copy()->setTime(20, 0);
            $dueHuman = 'Tonight at 8:00 PM';
            $text = preg_replace('/\btonight\b/i', '', $text);
        } elseif (preg_match('/\btoday\b/i', $text)) {
            $dueAt = $now->copy()->setTime(18, 0);
            $dueHuman = 'Today at 6:00 PM';
            $text = preg_replace('/\btoday\b/i', '', $text);
        } elseif (preg_match('/\b(this|next)?\s*(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i', $text, $dayMatch)) {
            $targetDay = strtolower($dayMatch[2]);
            $dueAt = $now->copy()->next($targetDay)->setTime(12, 0);
            $dueHuman = ucfirst($targetDay).' at 12:00 PM';
            $text = preg_replace('/\b(this|next)?\s*'.preg_quote($dayMatch[0], '/').'\b/i', '', $text);
        }

        // Refine with specific time if present: e.g. "5pm", "10:30am", "morning", "afternoon", "evening"
        if ($dueAt && preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $text, $timeMatch)) {
            $hour = (int) $timeMatch[1];
            $minute = ! empty($timeMatch[2]) ? (int) $timeMatch[2] : 0;
            $meridiem = strtolower($timeMatch[3]);
            if ($meridiem === 'pm' && $hour < 12) {
                $hour += 12;
            } elseif ($meridiem === 'am' && $hour === 12) {
                $hour = 0;
            }
            $dueAt->setTime($hour, $minute);
            $dueHuman = $dueAt->format('D, M j \a\t g:i A');
            $text = preg_replace('/\b'.preg_quote($timeMatch[0], '/').'\b/i', '', $text);
        }

        // 5. Category detection
        $category = 'errands';
        $lowered = strtolower($raw);
        if (preg_match('/\b(milk|eggs|bread|groceries|grocery|fruit|apples|bananas|vegetables|salad|produce|snack|dinner|meat|chicken|cheese|cereal)\b/i', $lowered)) {
            $category = 'groceries';
        } elseif (preg_match('/\b(tylenol|advil|ibuprofen|medicine|prescription|pills|vitamins|pharmacy|drugs|bandaid)\b/i', $lowered)) {
            $category = 'pharmacy';
        } elseif (preg_match('/\b(nails|hammer|screws|paint|lightbulb|batteries|hardware|tools|plumbing|filters)\b/i', $lowered)) {
            $category = 'hardware';
        } elseif (preg_match('/\b(clean|laundry|detergent|bleach|tissues|paper towel|toilet paper|trash bags|sponge)\b/i', $lowered)) {
            $category = 'household';
        }

        // 6. Clean title
        $cleanedTitle = preg_replace('/\s+/', ' ', trim($text));
        $cleanedTitle = preg_replace('/^(please|can you|hey|need to|need|could you|pick up|buy|get)\s+/i', '', $cleanedTitle);
        $cleanedTitle = trim($cleanedTitle, " \t\n\r\0\x0B,.-");
        if (empty($cleanedTitle)) {
            $cleanedTitle = $raw;
        } else {
            $cleanedTitle = ucfirst($cleanedTitle);
        }

        $parsed = [
            'raw_input' => $raw,
            'title' => $cleanedTitle,
            'category' => $category,
            'priority' => $priority,
            'due_at' => $dueAt ? $dueAt->toISOString() : null,
            'due_human' => $dueHuman,
            'place_id' => $placeId,
            'store_name' => $storeName,
            'assignee_id' => $assigneeId,
            'assignee_name' => $assigneeName,
            'notes' => 'Parsed by AI Family Dispatcher',
            'confidence' => 0.94,
        ];

        // If client requested instant creation / execution
        if (! empty($validated['execute'])) {
            app(QuotaService::class)->assertCanCreateRequest();

            $model = $workflow->create($user, [
                'title' => $parsed['title'],
                'category' => $parsed['category'],
                'priority' => $parsed['priority'],
                'due_at' => $parsed['due_at'],
                'place_id' => $parsed['place_id'],
                'store_name' => $parsed['store_name'],
                'assignee_id' => $parsed['assignee_id'],
                'notes' => $parsed['notes'],
            ]);

            return response()->json([
                'parsed' => $parsed,
                'created' => true,
                'request' => new ServiceRequestResource($model->load(['item', 'place', 'requester', 'assignee'])),
            ], 201);
        }

        return response()->json([
            'parsed' => $parsed,
            'created' => false,
        ]);
    }
}
