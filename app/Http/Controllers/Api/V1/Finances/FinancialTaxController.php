<?php

namespace App\Http\Controllers\Api\V1\Finances;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Resources\FinancialTaxResource;
use App\Models\FinancialTax;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialTaxController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Request $request): JsonResponse
    {
        $taxes = FinancialTax::query()
            ->when($request->filled('tax_year'), fn ($q) => $q->where('tax_year', (int) $request->input('tax_year')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderBy('due_date')
            ->orderByDesc('tax_year')
            ->get();

        return FinancialTaxResource::collection($taxes)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:property,income,municipal,sales,deduction,other'],
            'tax_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:pending,paid,filed,overdue'],
            'paid_at' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        $tenant = app(TenantContext::class)->tenant();
        $validated['currency'] = $validated['currency'] ?? ($tenant?->settings['currency'] ?? 'USD');
        $validated['created_by_id'] = $request->user()?->getKey();

        if (($validated['status'] ?? null) === 'paid' && empty($validated['paid_at'])) {
            $validated['paid_at'] = now();
        }

        $tax = FinancialTax::create($validated);

        $this->audit->record('finance.tax_created', [
            'tax_id' => $tax->id,
            'name' => $tax->name,
            'amount_minor' => $tax->amount_minor,
        ]);

        return (new FinancialTaxResource($tax))->response()->setStatusCode(201);
    }

    public function show(FinancialTax $tax): JsonResponse
    {
        return (new FinancialTaxResource($tax))->response();
    }

    public function update(Request $request, FinancialTax $tax): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:property,income,municipal,sales,deduction,other'],
            'tax_year' => ['sometimes', 'required', 'integer', 'min:2000', 'max:2100'],
            'amount_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:pending,paid,filed,overdue'],
            'paid_at' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        if (($validated['status'] ?? null) === 'paid' && empty($tax->paid_at) && empty($validated['paid_at'])) {
            $validated['paid_at'] = now();
        }

        $tax->update($validated);

        $this->audit->record('finance.tax_updated', [
            'tax_id' => $tax->id,
            'amount_minor' => $tax->amount_minor,
            'status' => $tax->status,
        ]);

        return (new FinancialTaxResource($tax))->response();
    }

    public function destroy(FinancialTax $tax): JsonResponse
    {
        $id = $tax->id;
        $name = $tax->name;
        $tax->delete();

        $this->audit->record('finance.tax_deleted', [
            'tax_id' => $id,
            'name' => $name,
        ]);

        return response()->json(['message' => 'Tax entry removed.']);
    }
}
