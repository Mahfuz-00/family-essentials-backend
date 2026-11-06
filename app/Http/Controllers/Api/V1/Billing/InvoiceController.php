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
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId && $invoice->tenant_id !== $tenantId && ! $request->user()?->is_platform_admin) {
            abort(404, 'Invoice not found.');
        }

        if ($invoice->payment_method === 'cash') {
            abort(400, 'Digital receipt download is not available for cash payments. A physical invoice is issued in person upon settlement.');
        }

        $formattedAmount = '$'.number_format($invoice->amount_cents / 100, 2);
        $paidDate = $invoice->paid_at ? $invoice->paid_at->format('M d, Y') : $invoice->created_at->format('M d, Y');
        $lineItems = $invoice->line_items ?? [
            ['description' => "FairShare {$invoice->tier} Subscription", 'amount_cents' => $invoice->amount_cents],
        ];

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
            <div class="badge">Status: '.$invoice->status.'</div>
            <div style="font-size: 14px; font-weight: bold; margin-top: 6px;">'.$invoice->invoice_number.'</div>
        </div>
    </div>

    <div class="details">
        <div>
            <strong>Billed To:</strong><br>
            '.$invoice->tenant->name.'<br>
            Household Workspace ID #'.$invoice->tenant_id.'
        </div>
        <div style="text-align: right;">
            <strong>Payment Date:</strong> '.$paidDate.'<br>
            <strong>Payment Method:</strong> '.ucwords(str_replace('_', ' ', $invoice->payment_method)).' (ending '.$invoice->last4.')<br>
            <strong>Plan Tier:</strong> '.ucfirst($invoice->tier).'
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
        Total Paid: '.$formattedAmount.' '.$invoice->currency.'
    </div>

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
            'auto_mark_paid' => true,
        ];

        return response()->json([
            'data' => [
                'active_gateway' => $activeGateway,
                'currency' => $settings['currency'] ?? 'USD',
                'cash' => [
                    'enabled' => (bool) ($cashSettings['enabled'] ?? true),
                    'instructions' => $cashSettings['instructions'] ?? 'Cash payment upon in-person settlement or physical invoice collection.',
                    'auto_mark_paid' => (bool) ($cashSettings['auto_mark_paid'] ?? true),
                ],
            ],
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
            'payment_method' => ['nullable', 'string', 'in:credit_card,cash,card,mock'],
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

        // 1. Update household tier
        $tenant->subscription_tier = $tier;
        $tenant->status = 'active';
        $tenant->save();

        $isCash = $paymentMethod === 'cash';
        $autoMarkPaid = $isCash ? ($cashSettings['auto_mark_paid'] ?? true) : true;
        $status = $autoMarkPaid ? 'paid' : 'pending';
        $paidAt = $autoMarkPaid ? now() : null;
        $last4 = $isCash ? 'CASH' : ($validated['last4'] ?? '4242');

        // 2. Automatically generate and record the invoice
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
            'line_items' => [
                [
                    'description' => "FairShare ".ucfirst($tier)." Plan ({$period})".($isCash ? ' — Cash In-Person' : ''),
                    'quantity' => 1,
                    'amount_cents' => $amountCents,
                ],
            ],
        ]);

        $this->audit->record('billing.invoice_generated', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount_cents' => $amountCents,
            'tier' => $tier,
        ], 'info');

        return response()->json([
            'message' => 'Subscription upgraded and invoice generated successfully.',
            'tenant' => $tenant,
            'invoice' => $invoice,
        ], 201);
    }
}
