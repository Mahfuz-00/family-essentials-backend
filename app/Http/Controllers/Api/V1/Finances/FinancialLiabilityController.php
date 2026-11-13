<?php

namespace App\Http\Controllers\Api\V1\Finances;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Http\Resources\FinancialLiabilityResource;
use App\Models\FinancialLiability;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialLiabilityController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Request $request): JsonResponse
    {
        $liabilities = FinancialLiability::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('remaining_balance_minor')
            ->get();

        return FinancialLiabilityResource::collection($liabilities)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:mortgage,loan,auto_loan,student_loan,personal_loan,credit_line'],
            'lender' => ['nullable', 'string', 'max:255'],
            'principal_amount_minor' => ['nullable', 'integer', 'min:0'],
            'remaining_balance_minor' => ['required', 'integer', 'min:0'],
            'monthly_payment_minor' => ['nullable', 'integer', 'min:0'],
            'interest_rate_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'due_day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['nullable', 'date'],
            'maturity_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', 'in:active,paid_off,refinanced'],
            'notes' => ['nullable', 'string'],
        ]);

        $tenant = app(TenantContext::class)->tenant();
        $validated['currency'] = $validated['currency'] ?? ($tenant?->settings['currency'] ?? 'USD');
        $validated['principal_amount_minor'] = $validated['principal_amount_minor'] ?? $validated['remaining_balance_minor'];
        $validated['created_by_id'] = $request->user()?->getKey();

        $liability = FinancialLiability::create($validated);

        $this->audit->record('finance.liability_created', [
            'liability_id' => $liability->id,
            'name' => $liability->name,
            'type' => $liability->type,
            'remaining_balance_minor' => $liability->remaining_balance_minor,
        ]);

        return (new FinancialLiabilityResource($liability))->response()->setStatusCode(201);
    }

    public function show(FinancialLiability $liability): JsonResponse
    {
        return (new FinancialLiabilityResource($liability))->response();
    }

    public function update(Request $request, FinancialLiability $liability): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:mortgage,loan,auto_loan,student_loan,personal_loan,credit_line'],
            'lender' => ['nullable', 'string', 'max:255'],
            'principal_amount_minor' => ['nullable', 'integer', 'min:0'],
            'remaining_balance_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'monthly_payment_minor' => ['nullable', 'integer', 'min:0'],
            'interest_rate_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'due_day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['nullable', 'date'],
            'maturity_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', 'in:active,paid_off,refinanced'],
            'notes' => ['nullable', 'string'],
        ]);

        $liability->update($validated);

        $this->audit->record('finance.liability_updated', [
            'liability_id' => $liability->id,
            'remaining_balance_minor' => $liability->remaining_balance_minor,
        ]);

        return (new FinancialLiabilityResource($liability))->response();
    }

    public function destroy(FinancialLiability $liability): JsonResponse
    {
        $id = $liability->id;
        $name = $liability->name;
        $liability->delete();

        $this->audit->record('finance.liability_deleted', [
            'liability_id' => $id,
            'name' => $name,
        ]);

        return response()->json(['message' => 'Liability removed.']);
    }
}
