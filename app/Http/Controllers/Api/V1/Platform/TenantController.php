<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\StoreTenantRequest;
use App\Http\Requests\Tenancy\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Tenancy\TenantProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform Admin tenant console. The `{tenant}` route parameter is resolved
 * explicitly (not via route-model binding) because binding runs before the
 * platform middleware enables the RLS bypass.
 */
class TenantController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('tier'), fn ($query) => $query->where('subscription_tier', $request->string('tier')))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->withCount('members')
            ->latest()
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return TenantResource::collection($tenants)->response();
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Tenant::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('tier'), fn ($q) => $q->where('subscription_tier', $request->string('tier')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->withCount('members');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['id', 'name', 'slug', 'status', 'subscription_tier', 'members', 'created_at']);

            $query->orderBy('id')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->id,
                        $row->name,
                        $row->slug,
                        $row->status,
                        $row->subscription_tier,
                        $row->members_count,
                        $row->created_at?->toISOString(),
                    ]);
                }
            });

            fclose($out);
        }, 'tenants.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(StoreTenantRequest $request, TenantProvisioner $provisioner): JsonResponse
    {
        $tenant = $provisioner->provision(
            name: (string) $request->string('name'),
            tier: (string) $request->input('subscription_tier', 'pilot'),
            status: (string) $request->input('status', 'trial'),
        );

        $this->audit->record('tenant.created', [
            'tenant' => $tenant->name,
            'tier' => $tenant->subscription_tier,
            'status' => $tenant->status,
        ], 'info', $tenant->id);

        return (new TenantResource($tenant))->response()->setStatusCode(201);
    }

    public function show(string $tenant): JsonResponse
    {
        return (new TenantResource($this->findTenant($tenant)->loadCount('members')))->response();
    }

    public function update(UpdateTenantRequest $request, string $tenant): JsonResponse
    {
        $model = $this->findTenant($tenant);
        $before = $model->only(['name', 'subscription_tier', 'status']);
        $model->update($request->validated());

        $this->audit->record('tenant.updated', [
            'before' => $before,
            'after' => $model->only(['name', 'subscription_tier', 'status']),
        ], 'info', $model->id);

        return (new TenantResource($model->loadCount('members')))->response();
    }

    public function suspend(string $tenant): JsonResponse
    {
        $model = $this->findTenant($tenant);
        $model->update(['status' => 'suspended', 'suspended_at' => now()]);

        $this->audit->record('tenant.suspended', ['tenant' => $model->name], 'warning', $model->id);

        return (new TenantResource($model))->response();
    }

    public function activate(string $tenant): JsonResponse
    {
        $model = $this->findTenant($tenant);
        $model->update(['status' => 'active', 'suspended_at' => null]);

        $this->audit->record('tenant.activated', ['tenant' => $model->name], 'info', $model->id);

        return (new TenantResource($model))->response();
    }

    public function bulkSuspend(Request $request): JsonResponse
    {
        return $this->bulkStatus($request, 'suspended');
    }

    public function bulkActivate(Request $request): JsonResponse
    {
        return $this->bulkStatus($request, 'active');
    }

    private function bulkStatus(Request $request, string $status): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $affected = Tenant::query()->whereIn('id', $validated['ids'])->update([
            'status' => $status,
            'suspended_at' => $status === 'suspended' ? now() : null,
        ]);

        $this->audit->record(
            $status === 'suspended' ? 'tenant.bulk_suspended' : 'tenant.bulk_activated',
            ['tenant_ids' => $validated['ids'], 'affected' => $affected],
            'warning',
        );

        return response()->json(['affected' => $affected]);
    }

    private function findTenant(string $id): Tenant
    {
        return Tenant::query()->findOrFail($id);
    }
}
