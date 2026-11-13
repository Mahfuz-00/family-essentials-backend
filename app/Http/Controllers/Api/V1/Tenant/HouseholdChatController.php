<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\VoiceCallLog;
use App\Plans\QuotaService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HouseholdChatController extends Controller
{
    /**
     * Return household members, chat thread, and active SaaS plan limits for
     * member-to-member chat and voice calling.
     */
    public function index(Request $request, QuotaService $quotas): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id();
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        abort_if($tenant === null, 404, 'No active household.');

        $plan = $quotas->planFor($tenant->subscription_tier);
        $chatCap = (int) ($plan['chat_messages_cap'] ?? 30);
        $callsCap = (int) ($plan['member_calls_cap'] ?? 0);

        $cacheKey = "household_chat_messages_{$tenant->id}";
        $messages = Cache::get($cacheKey);

        $members = $tenant->users()
            ->get(['users.id', 'users.name', 'users.email', 'users.phone'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->pivot->role ?? 'member',
                'online' => true,
            ])
            ->values();

        if (! is_array($messages) || empty($messages)) {
            $firstMember = $members->firstWhere('id', '!=', $request->user()?->id) ?? $members->first();
            $senderName = $firstMember['name'] ?? 'Sarah Rahman';
            $senderId = $firstMember['id'] ?? 2;

            $messages = [
                [
                    'id' => 1,
                    'sender_id' => $senderId,
                    'sender_name' => $senderName,
                    'recipient_id' => null,
                    'text' => 'Hey! Are you heading past the grocery store on your way home today?',
                    'created_at' => now()->subMinutes(24)->format('g:i A'),
                ],
                [
                    'id' => 2,
                    'sender_id' => $request->user()?->id ?? 1,
                    'sender_name' => $request->user()?->name ?? 'Tanvir Rahman',
                    'recipient_id' => null,
                    'text' => 'Yes! I checked the Pantry list—picking up milk, eggs, and sourdough.',
                    'created_at' => now()->subMinutes(18)->format('g:i A'),
                ],
            ];
            Cache::put($cacheKey, $messages, now()->addDays(30));
        }

        $usedCalls = VoiceCallLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->whereNotIn('status', ['skipped'])
            ->count();

        return response()->json([
            'plan' => [
                'key' => $plan['key'],
                'label' => $plan['label'],
                'price' => $plan['price'] ?? '$0',
                'chat_cap' => $chatCap,
                'chat_used' => count($messages),
                'calls_cap' => $callsCap,
                'calls_used' => $usedCalls,
            ],
            'members' => $members,
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message to another household member or the household room,
     * governed by the active SaaS subscription plan's chat_messages_cap.
     */
    public function store(Request $request, QuotaService $quotas): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'recipient_id' => ['nullable', 'integer'],
        ]);

        $tenantId = app(TenantContext::class)->id();
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        abort_if($tenant === null, 404, 'No active household.');

        $plan = $quotas->planFor($tenant->subscription_tier);
        $chatCap = (int) ($plan['chat_messages_cap'] ?? 30);

        $cacheKey = "household_chat_messages_{$tenant->id}";
        $messages = Cache::get($cacheKey, []);

        if ($chatCap > 0 && count($messages) >= $chatCap) {
            return response()->json([
                'message' => "Monthly household chat limit of {$chatCap} messages reached on your {$plan['label']} plan. Please upgrade your subscription to continue messaging.",
                'code' => 'plan_limit_exceeded',
                'chat_cap' => $chatCap,
                'chat_used' => count($messages),
            ], 403);
        }

        $user = $request->user();
        $newMessage = [
            'id' => count($messages) + 1,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'recipient_id' => $validated['recipient_id'] ?? null,
            'text' => trim($validated['text']),
            'created_at' => now()->format('g:i A'),
        ];

        $messages[] = $newMessage;
        Cache::put($cacheKey, $messages, now()->addDays(30));

        app(AuditRecorder::class)->record('household.chat_message_sent', [
            'sender' => $user->name,
            'recipient_id' => $validated['recipient_id'] ?? 'all',
        ], 'info');

        return response()->json([
            'data' => $newMessage,
            'chat_used' => count($messages),
            'chat_cap' => $chatCap,
            'remaining_messages' => max(0, $chatCap - count($messages)),
        ], 201);
    }
}
