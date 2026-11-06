<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\RefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public static function getDefaultPolicy(): array
    {
        return [
            'policy_markdown' => "# FairShare Household Operations — Subscription & Refund Policy\n\n### 7-Day Money-Back Guarantee (Monthly Plans)\nMonthly subscriptions are eligible for a 100% full refund within 7 calendar days of invoice issuance. After 7 days, monthly billing charges are non-refundable.\n\n### First-Month 80% Money-Back Guarantee (Annual Plans)\nAnnual subscriptions are eligible for an 80% refund within the first 30 days (first month), with 20% retained for early termination and payment infrastructure costs. After 30 days, annual subscriptions are non-refundable.\n\n### Subscription Plan Modification & Proration Windows\n- **Monthly Subscriptions**:\n  - **Days 0–3**: Plan changes are locked during the initial 3-day activation grace period.\n  - **Days 4–20**: Plan changes are permitted with a **50% mid-cycle adjustment credit** applied to the transition charge.\n  - **Day 21 onwards**: Plan changes are locked for the current cycle to protect against renewal race conditions; modifications take effect at next renewal.\n- **Annual Subscriptions**:\n  - **Months 1–3 (Days 0–90)**: Plan changes and tier adjustments are permitted.\n  - **After 3 Months**: Annual plan commitments are locked for the remainder of the term.",
            'refund_window_days' => 7,
            'monthly_refund_window_days' => 7,
            'monthly_refund_percent' => 100,
            'annual_refund_window_days' => 30,
            'annual_refund_percent' => 80,
            'monthly_change_lock_start_days' => 3,
            'monthly_change_lock_end_days' => 20,
            'monthly_change_adjustment_percent' => 50,
            'annual_change_window_months' => 3,
            'annual_change_window_days' => 90,
            'auto_approve_under_cents' => 0,
            'pro_rata_enabled' => true,
            'contact_email' => 'billing@fairshare.app',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $policy = array_merge(self::getDefaultPolicy(), PlatformSetting::get('refund_policy', []));

        $query = RefundRequest::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                $term = '%'.$request->string('search').'%';
                $sub->where('tenant_name', 'like', $term)
                    ->orWhere('user_email', 'like', $term)
                    ->orWhere('reason', 'like', $term);
            }))
            ->with(['processor:id,name'])
            ->latest();

        $refunds = $query->paginate(min(50, max(1, $request->integer('per_page', 15))));

        $totalRefundedCents = RefundRequest::query()->where('status', 'approved')->sum('amount_cents');
        $pendingCount = RefundRequest::query()->where('status', 'pending')->count();
        $approvedCount = RefundRequest::query()->where('status', 'approved')->count();
        $rejectedCount = RefundRequest::query()->where('status', 'rejected')->count();

        return response()->json([
            'policy' => $policy,
            'refunds' => $refunds,
            'stats' => [
                'total_refunded_cents' => (int) $totalRefundedCents,
                'pending_count' => $pendingCount,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'avg_turnaround_hours' => 14,
            ],
        ]);
    }

    public function updatePolicy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'policy_markdown' => ['required', 'string'],
            'refund_window_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'monthly_refund_window_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'monthly_refund_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'annual_refund_window_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'annual_refund_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'monthly_change_lock_start_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'monthly_change_lock_end_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'monthly_change_adjustment_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'annual_change_window_months' => ['nullable', 'integer', 'min:1', 'max:12'],
            'annual_change_window_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'auto_approve_under_cents' => ['required', 'integer', 'min:0'],
            'pro_rata_enabled' => ['required', 'boolean'],
            'contact_email' => ['required', 'email'],
        ]);

        $merged = array_merge(self::getDefaultPolicy(), $validated);
        PlatformSetting::set('refund_policy', $merged);

        $this->audit->record('platform.refund_policy_updated', [
            'monthly_window' => $merged['monthly_refund_window_days'],
            'annual_window' => $merged['annual_refund_window_days'],
            'monthly_adjustment' => $merged['monthly_change_adjustment_percent'],
        ], 'info');

        return response()->json([
            'message' => 'Refund and plan change policy updated.',
            'policy' => $merged,
        ]);
    }

    public function checkEligibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['nullable', 'integer'],
            'invoice_number' => ['nullable', 'string'],
            'tenant_id' => ['nullable', 'integer'],
        ]);

        $invoice = null;
        if (!empty($validated['invoice_id'])) {
            $invoice = \App\Models\Invoice::with('tenant')->find($validated['invoice_id']);
        } elseif (!empty($validated['invoice_number'])) {
            $invoice = \App\Models\Invoice::with('tenant')->where('invoice_number', $validated['invoice_number'])->first();
        } elseif (!empty($validated['tenant_id'])) {
            $invoice = \App\Models\Invoice::with('tenant')
                ->where('tenant_id', $validated['tenant_id'])
                ->where('status', 'paid')
                ->latest('id')
                ->first();
        }

        if (!$invoice) {
            return response()->json([
                'eligible' => false,
                'message' => 'No matching invoice found for evaluation.',
            ], 404);
        }

        $policy = array_merge(self::getDefaultPolicy(), PlatformSetting::get('refund_policy', []));
        $isAnnual = in_array(strtolower($invoice->billing_period), ['annual', 'yearly']);

        $chargeDate = $invoice->paid_at ?? $invoice->created_at;
        $daysElapsed = max(0, (int) $chargeDate->diffInDays(now()));

        if ($isAnnual) {
            $window = (int) ($policy['annual_refund_window_days'] ?? 30);
            $eligible = $daysElapsed <= $window;
            $percent = $eligible ? (int) ($policy['annual_refund_percent'] ?? 80) : 0;
            $amountCents = (int) round(($invoice->amount_cents * $percent) / 100);
            $reason = $eligible
                ? "Eligible for {$percent}% refund within first-month annual window ({$daysElapsed} of {$window} days elapsed)."
                : "Ineligible: Annual refund window of {$window} days has expired ({$daysElapsed} days elapsed).";
        } else {
            $window = (int) ($policy['monthly_refund_window_days'] ?? 7);
            $eligible = $daysElapsed <= $window;
            $percent = $eligible ? (int) ($policy['monthly_refund_percent'] ?? 100) : 0;
            $amountCents = (int) round(($invoice->amount_cents * $percent) / 100);
            $reason = $eligible
                ? "Eligible for {$percent}% full refund within {$window}-day monthly window ({$daysElapsed} of {$window} days elapsed)."
                : "Ineligible: Monthly refund window of {$window} days has expired ({$daysElapsed} days elapsed).";
        }

        return response()->json([
            'eligible' => $eligible,
            'days_elapsed' => $daysElapsed,
            'window_days' => $window,
            'billing_period' => $invoice->billing_period,
            'refund_percent' => $percent,
            'original_amount_cents' => $invoice->amount_cents,
            'recommended_refund_cents' => $amountCents,
            'reason' => $reason,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'tier' => $invoice->tier,
                'amount_cents' => $invoice->amount_cents,
                'payment_method' => $invoice->payment_method,
                'status' => $invoice->status,
                'paid_at' => $invoice->paid_at,
                'tenant_name' => $invoice->tenant?->name ?? 'Household',
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'integer'],
            'tenant_name' => ['required', 'string'],
            'user_email' => ['nullable', 'email'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'reason' => ['required', 'string'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $refund = RefundRequest::create([
            ...$validated,
            'status' => 'pending',
            'processed_by' => $request->user()?->id,
        ]);

        $this->audit->record('platform.refund_created', [
            'tenant_name' => $refund->tenant_name,
            'amount_cents' => $refund->amount_cents,
            'currency' => $refund->currency,
        ], 'info');

        return response()->json([
            'message' => 'Refund request recorded.',
            'refund' => $refund,
        ], 201);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $refund = RefundRequest::findOrFail($id);

        $refund->update([
            'status' => 'approved',
            'admin_notes' => $request->input('admin_notes', $refund->admin_notes),
            'processed_by' => $request->user()?->id,
            'processed_at' => now(),
        ]);

        $this->audit->record('platform.refund_approved', [
            'refund_id' => $refund->id,
            'tenant' => $refund->tenant_name,
            'amount_cents' => $refund->amount_cents,
        ], 'warning');

        return response()->json([
            'message' => 'Refund approved and marked for disbursement.',
            'refund' => $refund->load('processor:id,name'),
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['required', 'string'],
        ]);

        $refund = RefundRequest::findOrFail($id);

        $refund->update([
            'status' => 'rejected',
            'admin_notes' => $validated['admin_notes'],
            'processed_by' => $request->user()?->id,
            'processed_at' => now(),
        ]);

        $this->audit->record('platform.refund_rejected', [
            'refund_id' => $refund->id,
            'tenant' => $refund->tenant_name,
            'reason' => $validated['admin_notes'],
        ], 'warning');

        return response()->json([
            'message' => 'Refund request rejected.',
            'refund' => $refund->load('processor:id,name'),
        ]);
    }
}
