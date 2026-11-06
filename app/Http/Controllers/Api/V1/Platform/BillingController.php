<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\RefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->with(['tenant:id,name,slug,subscription_tier,status'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->string('payment_method')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('invoice_number', 'like', $term)
                        ->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', $term));
                });
            })
            ->latest('id');

        $invoices = $query->paginate(min(100, max(1, $request->integer('per_page', 20))));

        $totalRevenueCents = Invoice::where('status', 'paid')->sum('amount_cents');
        $pendingCashCount = Invoice::where('payment_method', 'cash')->where('status', 'pending')->count();
        $pendingCashCents = Invoice::where('payment_method', 'cash')->where('status', 'pending')->sum('amount_cents');
        $paidCount = Invoice::where('status', 'paid')->count();
        $totalCount = Invoice::count();

        return response()->json([
            'invoices' => $invoices,
            'stats' => [
                'total_count' => $totalCount,
                'paid_count' => $paidCount,
                'pending_cash_count' => $pendingCashCount,
                'pending_cash_cents' => (int) $pendingCashCents,
                'total_revenue_cents' => (int) $totalRevenueCents,
            ],
        ]);
    }

    public function pendingCash(Request $request): JsonResponse
    {
        $pending = Invoice::query()
            ->with(['tenant:id,name,slug,subscription_tier,status'])
            ->where('payment_method', 'cash')
            ->where('status', 'pending')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $pending,
            'count' => $pending->count(),
        ]);
    }

    public function approveCash(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->payment_method !== 'cash') {
            return response()->json([
                'message' => 'Only cash payment invoices can be processed via cash approval.',
            ], 422);
        }

        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'This invoice has already been marked as paid.',
            ], 422);
        }

        $invoice->status = 'paid';
        $invoice->paid_at = now();
        $invoice->save();

        // Activate the requested subscription tier for the household
        $tenant = $invoice->tenant;
        if ($tenant) {
            $tenant->subscription_tier = $invoice->tier;
            $tenant->status = 'active';
            $tenant->save();
        }

        $this->audit->record('platform.cash_payment_approved', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'tenant_id' => $invoice->tenant_id,
            'amount_cents' => $invoice->amount_cents,
            'tier' => $invoice->tier,
        ], 'info');

        return response()->json([
            'message' => "Cash payment of $".number_format($invoice->amount_cents / 100, 2)." approved. Invoice {$invoice->invoice_number} is now Paid and the household plan has been upgraded.",
            'invoice' => $invoice->fresh(['tenant']),
            'tenant' => $tenant,
        ]);
    }

    public function rejectCash(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->payment_method !== 'cash') {
            return response()->json([
                'message' => 'Only cash payment invoices can be rejected through this action.',
            ], 422);
        }

        $reason = $request->input('reason', 'Payment not received during in-person settlement.');

        $invoice->status = 'failed';
        $invoice->save();

        $this->audit->record('platform.cash_payment_rejected', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'tenant_id' => $invoice->tenant_id,
            'reason' => $reason,
        ], 'warning');

        return response()->json([
            'message' => "Cash payment request {$invoice->invoice_number} declined.",
            'invoice' => $invoice->fresh(['tenant']),
        ]);
    }

    public function downloadInvoice(Request $request, Invoice $invoice)
    {
        return app(\App\Http\Controllers\Api\V1\Billing\InvoiceController::class)->downloadHtml($request, $invoice);
    }

    public function notifications(Request $request): JsonResponse
    {
        $pendingCash = Invoice::query()
            ->with(['tenant:id,name,slug'])
            ->where('payment_method', 'cash')
            ->where('status', 'pending')
            ->latest('id')
            ->limit(10)
            ->get();

        $pendingRefunds = RefundRequest::query()
            ->where('status', 'pending')
            ->latest('id')
            ->limit(10)
            ->get();

        $cashCount = Invoice::where('payment_method', 'cash')->where('status', 'pending')->count();
        $refundCount = RefundRequest::where('status', 'pending')->count();

        return response()->json([
            'total_pending' => $cashCount + $refundCount,
            'pending_cash_count' => $cashCount,
            'pending_refund_count' => $refundCount,
            'cash_settlements' => $pendingCash,
            'refund_requests' => $pendingRefunds,
        ]);
    }
}
