<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Tenancy\TenantProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;

class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request, TenantProvisioner $provisioner): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'phone', 'password']));

        $currency = strtoupper((string) ($request->input('currency') ?: 'USD'));
        $monthlyBudget = $request->input('monthly_budget_minor');
        $yearlyBudget = $request->input('yearly_budget_minor');

        $tenant = $provisioner->provision(
            name: (string) ($request->input('household_name') ?: $user->name."'s Household"),
            owner: $user,
            currency: $currency,
            monthlyBudgetMinor: $monthlyBudget !== null ? (int) $monthlyBudget : null,
            yearlyBudgetMinor: $yearlyBudget !== null ? (int) $yearlyBudget : null,
        );

        $user->forceFill(['active_tenant_id' => $tenant->id])->save();

        event(new Registered($user));

        $token = $user->createToken(
            $request->input('device_name', 'web'),
            [AuthenticatedSessionController::abilityFor($request)],
        )->plainTextToken;

        app(\App\Audit\AuditRecorder::class)->record('auth.registered', [
            'household' => $tenant->name,
        ], 'info', $tenant->id);

        return response()->json([
            'user' => new UserResource($user->fresh()),
            'tenant' => new TenantResource($tenant),
            'token' => $token,
        ], 201);
    }
}
