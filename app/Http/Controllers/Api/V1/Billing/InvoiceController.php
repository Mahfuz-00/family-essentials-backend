<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    private function currentTenant(): Tenant
    {
        $tenantId = app(TenantContext::class)->id();
        return Tenant::findOrFail($tenantId);
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $this->currentTenant();

        $invoices = Invoice::where('tenant_id', $tenant->id)
            ->latest('id')
            ->paginate(min(50, max(1, $request->integer('per_page', 20))));

        return response()->json($invoices);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $tenant = $this->currentTenant();

        if ($invoice->tenant_id !== $tenant->id) {
            abort(404, 'Invoice not found.');
        }

        return response()->json(['data' => $invoice]);
    }

    public function downloadHtml(Request $request, Invoice $invoice): Response
    {
        $invoice->loadMissing('tenant');
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId && $invoice->tenant_id !== $tenantId && ! $request->user()?->is_platform_admin) {
            abort(404, 'Invoice not found.');
        }

        if ($invoice->payment_method === 'cash' && ! $request->user()?->is_platform_admin) {
            abort(400, 'Digital receipt download is not available for cash payments. A physical invoice is issued in person upon settlement.');
        }

        $formattedAmount = '$'.number_format($invoice->amount_cents / 100, 2);
        $paidDate = $invoice->paid_at ? $invoice->paid_at->format('M d, Y') : $invoice->created_at->format('M d, Y');
        $lineItems = $invoice->line_items ?? [
            ['description' => "FairShare {$invoice->tier} Subscription", 'amount_cents' => $invoice->amount_cents],
        ];

        $isCash = $invoice->payment_method === 'cash';
        $badgeText = $isCash
            ? ($invoice->status === 'paid' ? 'Paid in Cash (In-Person)' : 'Pending In-Person Cash Settlement')
            : 'Status: '.$invoice->status;

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice '.$invoice->invoice_number.' — FairShare</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #191c1e; max-width: 780px; margin: 40px auto; padding: 30px; border: 1px solid #d8e2dc; border-radius: 12px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #376847; padding-bottom: 20px; }
        .brand { font-size: 24px; font-weight: 800; color: #376847; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; background: #e5ede7; color: #284e34; text-transform: uppercase; }
        .details { margin: 24px 0; display: flex; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; margin: 24px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5ede7; }
        th { background: #fdfcf7; color: #4d6054; font-size: 13px; text-transform: uppercase; }
        .total { text-align: right; font-size: 20px; font-weight: 800; color: #376847; margin-top: 20px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5ede7; text-align: center; color: #737974; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">FairShare</div>
            <div style="font-size: 13px; color: #4d6054;">Household Operations &amp; Shared Accountability</div>
        </div>
        <div>
            <div class="badge">'.$badgeText.'</div>
            <div style="font-size: 14px; font-weight: bold; margin-top: 6px;">'.$invoice->invoice_number.'</div>
        </div>
    </div>

    <div class="details">
        <div>
            <strong>Billed To:</strong><br>
            '.htmlspecialchars($invoice->tenant?->name ?? ('Household #'.$invoice->tenant_id)).'<br>
            Household Workspace ID #'.$invoice->tenant_id.'
        </div>
        <div style="text-align: right;">
            <strong>Payment Date:</strong> '.$paidDate.'<br>
            <strong>Payment Method:</strong> '.($isCash ? 'Cash / In-Person Handover' : ucwords(str_replace('_', ' ', $invoice->payment_method)).' (ending '.$invoice->last4.')').'<br>
            <strong>Plan Tier:</strong> '.ucfirst($invoice->tier).' ('.ucfirst($invoice->billing_period).')
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($lineItems as $item) {
            $itemAmount = '$'.number_format(($item['amount_cents'] ?? 0) / 100, 2);
            $html .= '<tr>
                <td>'.htmlspecialchars($item['description'] ?? 'Subscription').'</td>
                <td style="text-align: right; font-weight: bold;">'.$itemAmount.'</td>
            </tr>';
        }

        $html .= '</tbody>
    </table>

    <div class="total">
        Total: '.$formattedAmount.' '.$invoice->currency.'
    </div>';

        if ($isCash) {
            $html .= '
    <div style="margin-top: 30px; padding: 18px; border: 2px dashed #376847; border-radius: 10px; background: #fdfcf7;">
        <div style="font-weight: 800; font-size: 13px; color: #284e34; text-transform: uppercase; margin-bottom: 6px;">
            Physical In-Person Cash Settlement Handover Receipt
        </div>
        <div style="font-size: 12px; color: #4d6054; margin-bottom: 14px;">
            This document serves as the official printed invoice voucher handed to the household client upon in-person cash settlement.
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 12px; color: #191c1e; padding-top: 10px; border-top: 1px solid #d8e2dc;">
            <div><strong>Collected By (Platform Admin):</strong> ______________________</div>
            <div><strong>Date of Handover:</strong> _________________</div>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 12px; color: #191c1e; margin-top: 16px;">
            <div><strong>Household Client Signature:</strong> __________________________</div>
            <div><strong>Amount Received:</strong> '.$formattedAmount.' '.$invoice->currency.'</div>
        </div>
    </div>';
        }

        $html .= '
    <div class="footer">
        Thank you for trusting FairShare with your household coordination.<br>
        Questions? Contact support@fairshare.app
    </div>
</body>
</html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    public function createPortalSession(Request $request): JsonResponse
    {
        $tenant = $this->currentTenant();

        // Stripe Customer Portal URL or sandbox test link
        $portalUrl = config('services.stripe.customer_portal_url') ?? (url('/billing?session=portal_mock_'.time()));

        $this->audit->record('billing.portal_accessed', [
            'tenant_id' => $tenant->id,
        ], 'info');

        return response()->json([
            'url' => $portalUrl,
            'message' => 'Billing customer portal session initialized.',
        ]);
    }

    public function paymentMethods(): JsonResponse
    {
        $settings = \App\Models\PlatformSetting::get('payment_gateway', []);
        $activeGateway = $settings['active_gateway'] ?? 'stripe';
        $gateways = $settings['gateways'] ?? [];
        $cashSettings = $gateways['cash'] ?? [
            'enabled' => true,
            'instructions' => 'Cash payment upon in-person settlement or physical invoice collection.',
            'auto_mark_paid' => false,
        ];
        $bankSettings = $gateways['bank_transfer'] ?? ['enabled' => false];

        return response()->json([
            'data' => [
                'active_gateway' => $activeGateway,
                'mode' => $settings['mode'] ?? 'sandbox',
                'is_sandbox' => ($settings['mode'] ?? 'sandbox') === 'sandbox',
                'currency' => $settings['currency'] ?? 'USD',
                'cash' => [
                    'enabled' => (bool) ($cashSettings['enabled'] ?? true),
                    'instructions' => $cashSettings['instructions'] ?? 'Cash payment upon in-person settlement or physical invoice collection.',
                    'auto_mark_paid' => (bool) ($cashSettings['auto_mark_paid'] ?? false),
                ],
                'bank_transfer' => [
                    'enabled' => (bool) ($bankSettings['enabled'] ?? false),
                    'instructions' => $bankSettings['instructions'] ?? 'Please reference your invoice number when sending wire transfers.',
                    'bank_name' => $bankSettings['bank_name'] ?? '',
                    'account_number' => $bankSettings['account_number'] ?? '',
                ],
            ],
        ]);
    }

    public function changePlanPreview(Request $request): JsonResponse
    {
        $tenant = $this->currentTenant();
        $targetTier = $request->input('tier', 'family');
        $targetPeriod = in_array($request->input('billing_period', 'monthly'), ['annual', 'yearly']) ? 'annual' : 'monthly';

        $prices = [
            'starter' => 0,
            'plus' => 900,
            'family' => 1900,
            'family_plus' => 2900,
            'enterprise' => 4900,
        ];

        $baseAmountCents = $prices[$targetTier] ?? 1900;
        if ($targetPeriod === 'annual') {
            $baseAmountCents = (int) round($baseAmountCents * 10);
        }

        $latestPaidInvoice = Invoice::where('tenant_id', $tenant->id)
            ->where('status', 'paid')
            ->latest('id')
            ->first();

        if (! $latestPaidInvoice || $latestPaidInvoice->amount_cents === 0 || in_array($tenant->subscription_tier, ['starter', 'pilot'])) {
            return response()->json([
                'eligible' => true,
                'days_elapsed' => 0,
                'current_tier' => $tenant->subscription_tier,
                'target_tier' => $targetTier,
                'target_period' => $targetPeriod,
                'base_amount_cents' => $baseAmountCents,
                'discount_cents' => 0,
                'final_amount_cents' => $baseAmountCents,
                'reason' => 'First-time plan selection (no cycle restrictions apply).',
            ]);
        }

        $policy = array_merge(\App\Http\Controllers\Api\V1\Platform\RefundController::getDefaultPolicy(), \App\Models\PlatformSetting::get('refund_policy', []));
        $currentPeriod = in_array(strtolower($latestPaidInvoice->billing_period), ['annual', 'yearly']) ? 'annual' : 'monthly';
        $cycleStart = $latestPaidInvoice->period_start ?? $latestPaidInvoice->paid_at ?? $latestPaidInvoice->created_at;
        $daysElapsed = max(0, (int) $cycleStart->diffInDays(now()));

        if ($currentPeriod === 'monthly') {
            $lockStart = (int) ($policy['monthly_change_lock_start_days'] ?? 3);
            $lockEnd = (int) ($policy['monthly_change_lock_end_days'] ?? 20);

            if ($daysElapsed < $lockStart) {
                return response()->json([
                    'eligible' => false,
                    'days_elapsed' => $daysElapsed,
                    'reason' => "Plan changes are locked during the initial {$lockStart}-day activation period ({$daysElapsed} of {$lockStart} days elapsed).",
                    'policy_code' => 'monthly_early_lock',
                ]);
            }

            if ($daysElapsed > $lockEnd) {
                return response()->json([
                    'eligible' => false,
                    'days_elapsed' => $daysElapsed,
                    'reason' => "Plan modifications for this monthly cycle closed on Day {$lockEnd} ({$daysElapsed} days elapsed). Changes will apply at your next billing renewal.",
                    'policy_code' => 'monthly_late_freeze',
                ]);
            }

            $adjustmentPercent = (int) ($policy['monthly_change_adjustment_percent'] ?? 50);
            $discountCents = (int) round(($baseAmountCents * $adjustmentPercent) / 100);
            $finalAmountCents = max(0, $baseAmountCents - $discountCents);

            return response()->json([
                'eligible' => true,
                'days_elapsed' => $daysElapsed,
                'current_tier' => $tenant->subscription_tier,
                'target_tier' => $targetTier,
                'target_period' => $targetPeriod,
                'base_amount_cents' => $baseAmountCents,
                'discount_cents' => $discountCents,
                'adjustment_percent' => $adjustmentPercent,
                'final_amount_cents' => $finalAmountCents,
                'reason' => "Day {$daysElapsed} of monthly cycle: Eligible with a {$adjustmentPercent}% mid-cycle adjustment credit.",
            ]);
        }

        // Annual
        $annualLockDays = (int) ($policy['annual_change_window_days'] ?? 90);
        if ($daysElapsed > $annualLockDays) {
            return response()->json([
                'eligible' => false,
                'days_elapsed' => $daysElapsed,
                'reason' => "Annual plan modifications are locked after 3 months ({$annualLockDays} days). Currently {$daysElapsed} days elapsed.",
                'policy_code' => 'annual_late_freeze',
            ]);
        }

        return response()->json([
            'eligible' => true,
            'days_elapsed' => $daysElapsed,
            'current_tier' => $tenant->subscription_tier,
            'target_tier' => $targetTier,
            'target_period' => $targetPeriod,
            'base_amount_cents' => $baseAmountCents,
            'discount_cents' => 0,
            'final_amount_cents' => $baseAmountCents,
            'reason' => "Within 3-month annual plan modification window ({$daysElapsed} of {$annualLockDays} days elapsed).",
        ]);
    }

    /**
     * Simulation of plan upgrade/payment: generates an official invoice upon completion
     */
    public function simulateUpgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tier' => ['required', 'string', 'in:starter,plus,family,family_plus,enterprise'],
            'billing_period' => ['nullable', 'string', 'in:monthly,annual,yearly'],
            'payment_method' => ['nullable', 'string', 'in:credit_card,cash,card,mock,stripe,paypal,razorpay,sslcommerz,bkash,nagad,paystack,mollie,bank_transfer'],
            'last4' => ['nullable', 'string'],
        ]);

        $tenant = $this->currentTenant();
        $tier = $validated['tier'];
        $period = in_array($validated['billing_period'] ?? 'monthly', ['annual', 'yearly']) ? 'annual' : 'monthly';

        $paymentMethod = $validated['payment_method'] ?? 'credit_card';
        if ($paymentMethod === 'card') {
            $paymentMethod = 'credit_card';
        }

        $platformSettings = \App\Models\PlatformSetting::get('payment_gateway', []);
        $cashSettings = $platformSettings['gateways']['cash'] ?? ['enabled' => true, 'auto_mark_paid' => true];

        if ($paymentMethod === 'cash' && empty($cashSettings['enabled'])) {
            return response()->json([
                'message' => 'Cash payment is currently disabled by platform administration.',
            ], 422);
        }

        $prices = [
            'starter' => 0,
            'plus' => 900,
            'family' => 1900,
            'family_plus' => 2900,
            'enterprise' => 4900,
        ];

        $amountCents = $prices[$tier] ?? 1900;
        if ($period === 'annual') {
            $amountCents = (int) round($amountCents * 10); // 2 months free on annual
        }

        // SaaS Subscription Plan Modification Policy Verification
        $latestPaidInvoice = Invoice::where('tenant_id', $tenant->id)
            ->where('status', 'paid')
            ->latest('id')
            ->first();

        $discountCents = 0;
        $adjustmentPercent = 0;
        $originalAmountCents = $amountCents;

        if ($latestPaidInvoice && $latestPaidInvoice->amount_cents > 0 && ! in_array($tenant->subscription_tier, ['starter', 'pilot'])) {
            $policy = array_merge(\App\Http\Controllers\Api\V1\Platform\RefundController::getDefaultPolicy(), \App\Models\PlatformSetting::get('refund_policy', []));
            $currentPeriod = in_array(strtolower($latestPaidInvoice->billing_period), ['annual', 'yearly']) ? 'annual' : 'monthly';
            $cycleStart = $latestPaidInvoice->period_start ?? $latestPaidInvoice->paid_at ?? $latestPaidInvoice->created_at;
            $daysElapsed = max(0, (int) $cycleStart->diffInDays(now()));

            if ($currentPeriod === 'monthly') {
                $lockStart = (int) ($policy['monthly_change_lock_start_days'] ?? 3);
                $lockEnd = (int) ($policy['monthly_change_lock_end_days'] ?? 20);

                if ($daysElapsed < $lockStart) {
                    return response()->json([
                        'message' => 'Plan change is not possible before 3 days of activation (grace period).',
                        'policy_code' => 'monthly_early_lock',
                        'days_elapsed' => $daysElapsed,
                        'window_start_day' => $lockStart,
                    ], 422);
                }

                if ($daysElapsed > $lockEnd) {
                    return response()->json([
                        'message' => 'Plan change is not possible after 20 days of the monthly billing cycle. Changes will apply at your next billing renewal.',
                        'policy_code' => 'monthly_late_freeze',
                        'days_elapsed' => $daysElapsed,
                        'window_end_day' => $lockEnd,
                    ], 422);
                }

                $adjustmentPercent = (int) ($policy['monthly_change_adjustment_percent'] ?? 50);
                $discountCents = (int) round(($amountCents * $adjustmentPercent) / 100);
                $amountCents = max(0, $amountCents - $discountCents);
            } else {
                $annualWindowDays = (int) ($policy['annual_change_window_days'] ?? 90);
                if ($daysElapsed > $annualWindowDays) {
                    return response()->json([
                        'message' => 'Plan change is not possible after 3 months (90 days) of an annual plan.',
                        'policy_code' => 'annual_late_freeze',
                        'days_elapsed' => $daysElapsed,
                        'window_days' => $annualWindowDays,
                    ], 422);
                }
            }
        }

        $isCash = $paymentMethod === 'cash';
        $autoMarkPaid = $isCash ? (bool) ($cashSettings['auto_mark_paid'] ?? false) : true;
        $status = $autoMarkPaid ? 'paid' : 'pending';
        $paidAt = $autoMarkPaid ? now() : null;
        $last4 = $isCash ? 'CASH' : ($validated['last4'] ?? '4242');

        // 1. If paid immediately (card or auto-approved cash), update household tier
        if ($autoMarkPaid) {
            $tenant->subscription_tier = $tier;
            $tenant->status = 'active';
            $tenant->save();
        }

        // 2. Prepare line items
        $lineItems = [
            [
                'description' => "FairShare ".ucfirst($tier)." Plan ({$period})".($isCash ? ' — Cash In-Person' : ''),
                'quantity' => 1,
                'amount_cents' => $originalAmountCents,
            ],
        ];

        if ($discountCents > 0) {
            $lineItems[] = [
                'description' => "Mid-Cycle Proration Adjustment ({$adjustmentPercent}% Credit)",
                'quantity' => 1,
                'amount_cents' => -$discountCents,
            ];
        }

        // 3. Automatically generate and record the invoice
        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'invoice_number' => Invoice::generateNumber(),
            'amount_cents' => $amountCents,
            'currency' => $platformSettings['currency'] ?? 'USD',
            'status' => $status,
            'tier' => $tier,
            'billing_period' => $period,
            'period_start' => now(),
            'period_end' => $period === 'annual' ? now()->addYear() : now()->addMonth(),
            'paid_at' => $paidAt,
            'payment_method' => $paymentMethod,
            'last4' => $last4,
            'line_items' => $lineItems,
        ]);

        $this->audit->record('billing.invoice_generated', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount_cents' => $amountCents,
            'tier' => $tier,
            'payment_method' => $paymentMethod,
            'status' => $status,
        ], 'info');

        $message = $autoMarkPaid
            ? 'Subscription upgraded and invoice generated successfully.'
            : 'Cash payment request submitted. Awaiting in-person settlement and platform admin approval.';

        return response()->json([
            'message' => $message,
            'tenant' => $tenant->fresh(),
            'invoice' => $invoice,
        ], 201);
    }
}
