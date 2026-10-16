<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Plans\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plan limits and live usage for the household.
 *
 * The dashboard and the app both read their seat and place caps from here, so
 * the number a household is shown is the number the API enforces — the previous
 * arrangement had caps only in the dashboard, enforcing nothing.
 */
class QuotaController extends Controller
{
    public function index(Request $request, QuotaService $quotas): JsonResponse
    {
        $tenant = $quotas->tenant();

        abort_if($tenant === null, 404, 'No active household.');

        return response()->json($quotas->report($tenant));
    }
}
