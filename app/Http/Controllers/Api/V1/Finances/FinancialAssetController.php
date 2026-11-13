<?php

namespace App\Http\Controllers\Api\V1\Finances;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Resources\FinancialAssetResource;
use App\Models\FinancialAsset;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialAssetController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Request $request): JsonResponse
    {
        $assets = FinancialAsset::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->orderByDesc('balance_minor')
            ->get();

        return FinancialAssetResource::collection($assets)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:savings,investment,stocks,retirement,crypto,real_estate,cash,other'],
            'institution' => ['nullable', 'string', 'max:255'],
            'balance_minor' => ['required', 'integer', 'min:0'],
            'contribution_monthly_minor' => ['nullable', 'integer', 'min:0'],
            'return_rate_bps' => ['nullable', 'integer', 'min:-5000', 'max:10000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        $tenant = app(TenantContext::class)->tenant();
        $validated['currency'] = $validated['currency'] ?? ($tenant?->settings['currency'] ?? 'USD');
        $validated['created_by_id'] = $request->user()?->getKey();

        $asset = FinancialAsset::create($validated);

        $this->audit->record('finance.asset_created', [
            'asset_id' => $asset->id,
            'name' => $asset->name,
            'type' => $asset->type,
            'balance_minor' => $asset->balance_minor,
        ]);

        return (new FinancialAssetResource($asset))->response()->setStatusCode(201);
    }

    public function show(FinancialAsset $asset): JsonResponse
    {
        return (new FinancialAssetResource($asset))->response();
    }

    public function update(Request $request, FinancialAsset $asset): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:savings,investment,stocks,retirement,crypto,real_estate,cash,other'],
            'institution' => ['nullable', 'string', 'max:255'],
            'balance_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'contribution_monthly_minor' => ['nullable', 'integer', 'min:0'],
            'return_rate_bps' => ['nullable', 'integer', 'min:-5000', 'max:10000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        $asset->update($validated);

        $this->audit->record('finance.asset_updated', [
            'asset_id' => $asset->id,
            'balance_minor' => $asset->balance_minor,
        ]);

        return (new FinancialAssetResource($asset))->response();
    }

    public function destroy(FinancialAsset $asset): JsonResponse
    {
        $id = $asset->id;
        $name = $asset->name;
        $asset->delete();

        $this->audit->record('finance.asset_deleted', [
            'asset_id' => $id,
            'name' => $name,
        ]);

        return response()->json(['message' => 'Asset removed.']);
    }
}
