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

        $currentSettings = $tenant->settings ?? [];
        $newSettings = array_merge($currentSettings, $request->input('settings', []));

        $tenant->update(['settings' => $newSettings]);

        return (new TenantResource($tenant))->response();
    }

    public function export(): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id();
        $tenant = Tenant::query()->with('members')->findOrFail($tenantId);

        $requests = \App\Models\ServiceRequest::query()
            ->where('tenant_id', $tenantId)
            ->get();

        $places = \App\Models\Place::query()
            ->where('tenant_id', $tenantId)
            ->get();

        $essentials = \App\Models\Essential::query()
            ->where('tenant_id', $tenantId)
            ->get();

        $ledgers = \App\Models\Ledger::query()
            ->where('tenant_id', $tenantId)
            ->with('entries')
            ->get();

        $export = [
            'format' => 'family_essentials_vault_v1',
            'exported_at' => now()->toIso8601String(),
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'subscription_tier' => $tenant->subscription_tier,
                'created_at' => $tenant->created_at?->toIso8601String(),
            ],
            'members' => $tenant->members->map(fn ($m) => [
                'name' => $m->name,
                'email' => $m->email,
                'phone' => $m->phone,
                'role' => $m->pivot->role ?? 'member',
                'joined_at' => $m->pivot->created_at ?? null,
            ]),
            'requests' => $requests,
            'places' => $places,
            'pantry_essentials' => $essentials,
            'ledgers' => $ledgers,
        ];

        return response()->json([
            'data' => $export,
        ], 200, [
            'Content-Disposition' => 'attachment; filename="household-vault-'.$tenant->slug.'.json"',
        ]);
    }
}
