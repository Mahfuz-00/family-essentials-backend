<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\GeofenceEvent;
use App\Models\LedgerEntry;
use App\Models\Place;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\VoiceCallLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Tier monthly pricing model in whole dollars
     */
    private const TIER_PRICES = [
        'pilot' => 0,
        'starter' => 0,
        'plus' => 9,
        'family' => 19,
        'enterprise' => 49,
    ];

    public function index(Request $request): JsonResponse
    {
        // 1. Household Status Breakdown
        $tenantsQuery = Tenant::query();
        $totalTenants = $tenantsQuery->count();
        $byStatus = Tenant::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $byTier = Tenant::query()
            ->selectRaw('subscription_tier, count(*) as count')
            ->groupBy('subscription_tier')
            ->pluck('count', 'subscription_tier')
            ->all();

        // 2. Financial & Revenue Metrics
        $mrr = 0;
        foreach ($byTier as $tier => $count) {
            $price = self::TIER_PRICES[strtolower($tier)] ?? 0;
            $mrr += ($price * $count);
        }
        $arr = $mrr * 12;

        // 3. User & Member Metrics
        $totalUsers = User::query()->count();
        $totalMembers = TenantUser::query()->count();
        $activeMembers = TenantUser::query()->where('status', 'active')->count();

        // 4. Task & Errand Execution Velocity
        $totalRequests = ServiceRequest::query()->count();
        $completedRequests = ServiceRequest::query()->where('status', 'completed')->count();
        $inProgressRequests = ServiceRequest::query()->whereIn('status', ['accepted', 'in_progress'])->count();
        $pendingRequests = ServiceRequest::query()->where('status', 'pending')->count();
        $completionRate = $totalRequests > 0 ? round(($completedRequests / $totalRequests) * 100, 1) : 0;

        // 5. Hardware Geofencing & Voice Escalation Signals
        $totalPlaces = Place::query()->count();
        $geofenceEvents = GeofenceEvent::query()->count();
        $voiceCalls = VoiceCallLog::query()->count();
        $deviceTokens = DeviceToken::query()->count();

        // 6. Household Money Operations
        $totalLedgerEntries = LedgerEntry::query()->count();
        $totalExpenseMinor = (int) LedgerEntry::query()->sum('amount_minor');

        // 7. Monthly Historical Timeseries (Last 6 months)
        $now = Carbon::now();
        $timeseries = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $now->copy()->subMonths($i)->endOfMonth();
            $label = $start->format('M Y');

            $monthTenants = Tenant::query()
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $monthRequests = ServiceRequest::query()
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $monthCompleted = ServiceRequest::query()
                ->where('status', 'completed')
                ->whereBetween('updated_at', [$start, $end])
                ->count();

            $timeseries[] = [
                'period' => $label,
                'signups' => $monthTenants,
                'requests_created' => $monthRequests,
                'requests_completed' => $monthCompleted,
                'estimated_mrr' => $mrr,
            ];
        }

        return response()->json([
            'overview' => [
                'mrr_dollars' => $mrr,
                'arr_dollars' => $arr,
                'total_households' => $totalTenants,
                'active_households' => $byStatus['active'] ?? 0,
                'trial_households' => $byStatus['trial'] ?? 0,
                'suspended_households' => $byStatus['suspended'] ?? 0,
                'total_users' => $totalUsers,
                'active_members' => $activeMembers,
            ],
            'by_status' => $byStatus,
            'by_tier' => $byTier,
            'errand_metrics' => [
                'total_requests' => $totalRequests,
                'completed_requests' => $completedRequests,
                'in_progress_requests' => $inProgressRequests,
                'pending_requests' => $pendingRequests,
                'completion_rate_percent' => $completionRate,
            ],
            'infrastructure' => [
                'saved_places' => $totalPlaces,
                'geofence_triggers' => $geofenceEvents,
                'voice_escalations' => $voiceCalls,
                'registered_devices' => $deviceTokens,
                'ledger_entries' => $totalLedgerEntries,
                'total_expenses_cents' => $totalExpenseMinor,
            ],
            'timeseries' => $timeseries,
        ]);
    }
}
