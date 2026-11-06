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

    public function index(Request $request): JsonResponse
    {
        $policy = PlatformSetting::get('refund_policy', [
            'policy_markdown' => "# FairShare Household Operations — Refund Policy\n\nWe want every household to thrive with FairShare. If you are not completely satisfied with your subscription, we offer an open and fair refund policy.\n\n### 14-Day Money-Back Guarantee\nAll new paid subscriptions (Plus, Family, and Enterprise) come with an unconditional 14-day money-back guarantee from the date of the first charge.\n\n### Pro-Rata Annual Refunds\nFor annual plans cancelled after the initial 14-day guarantee period, we credit pro-rated refunds for all remaining unused full calendar months.\n\n### Processing Timeline\nRefund requests submitted through billing or platform administration are reviewed within 2 business days. Approved refunds are credited directly back to the original payment method.",
            'refund_window_days' => 14,
            'auto_approve_under_cents' => 0,
            'pro_rata_enabled' => true,
            'contact_email' => 'billing@fairshare.app',
        ]);

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
            'refund_window_days' => ['required', 'integer', 'min:1', 'max:90'],
            'auto_approve_under_cents' => ['required', 'integer', 'min:0'],
            'pro_rata_enabled' => ['required', 'boolean'],
            'contact_email' => ['required', 'email'],
        ]);

        PlatformSetting::set('refund_policy', $validated);

        $this->audit->record('platform.refund_policy_updated', [
            'window_days' => $validated['refund_window_days'],
            'auto_approve' => $validated['auto_approve_under_cents'],
        ], 'info');

        return response()->json([
            'message' => 'Refund policy updated.',
            'policy' => $validated,
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
