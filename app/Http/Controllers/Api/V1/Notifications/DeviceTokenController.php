<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\StoreDeviceTokenRequest;
use App\Http\Resources\DeviceTokenResource;
use App\Models\DeviceToken;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $value = (string) $request->string('token');

        // A device can move between households, so clear any existing owner first
        // (runs under the audited bypass because the row may belong elsewhere).
        Tenancy::bypass(fn () => DeviceToken::withoutGlobalScope('tenant')
            ->where('token', $value)
            ->delete());

        $token = DeviceToken::create([
            'tenant_id' => app(TenantContext::class)->id(),
            'user_id' => $request->user()->getKey(),
            'platform' => (string) $request->string('platform'),
            'token' => $value,
            'last_used_at' => now(),
        ]);

        return (new DeviceTokenResource($token))->response()->setStatusCode(201);
    }

    public function destroy(DeviceToken $deviceToken): JsonResponse
    {
        abort_unless(
            (int) $deviceToken->user_id === (int) request()->user()->getKey(),
            403,
            'You can only revoke your own device tokens.'
        );

        $deviceToken->delete();

        return response()->json(['message' => 'Device token revoked.']);
    }
}
