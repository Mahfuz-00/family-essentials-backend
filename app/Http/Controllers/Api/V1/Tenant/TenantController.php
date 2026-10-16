<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\UpdateSettingsRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function show(): JsonResponse
    {
        $tenant = Tenant::query()
            ->withCount('members')
            ->findOrFail(app(TenantContext::class)->id());

        return (new TenantResource($tenant))->response();
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());

        $tenant->update(['settings' => $request->input('settings')]);

        return (new TenantResource($tenant))->response();
    }
}
