<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class MetricsController extends Controller
{
    public function index(): JsonResponse
    {
        $since = Carbon::now()->subDay();

        return response()->json([
            'total_tenants' => Tenant::query()->count(),
            'by_status' => Tenant::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'by_tier' => Tenant::query()
                ->selectRaw('subscription_tier, count(*) as total')
                ->groupBy('subscription_tier')
                ->pluck('total', 'subscription_tier'),
            'active_members' => TenantUser::withoutGlobalScope('tenant')
                ->where('status', 'active')
                ->count(),
            // Real security telemetry, counted from the audit ledger.
            'audit' => [
                'events_24h' => AuditEvent::query()->where('occurred_at', '>=', $since)->count(),
                'critical_24h' => AuditEvent::query()
                    ->where('occurred_at', '>=', $since)
                    ->where('level', 'critical')
                    ->count(),
                'warning_24h' => AuditEvent::query()
                    ->where('occurred_at', '>=', $since)
                    ->where('level', 'warning')
                    ->count(),
                'denied_platform_access_24h' => AuditEvent::query()
                    ->where('occurred_at', '>=', $since)
                    ->where('action', 'auth.platform_access_denied')
                    ->count(),
                'total' => AuditEvent::query()->count(),
                'oldest' => AuditEvent::query()->min('occurred_at'),
            ],
        ]);
    }
}
