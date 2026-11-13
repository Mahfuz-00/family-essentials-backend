<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Audit\AuditRecorder;
use App\Billing\PlanPricing;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Payments\PaymentGatewayManager;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Starts a hosted checkout for a plan change. The invoice stays pending until
 * the provider's signed webhook confirms the payment.
 */
class PaymentCheckoutController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tier' => ['required', 'string', 'in:starter,plus,family,family_plus,enterprise'],
            'billing_period' => ['nullable', 'string', 'in:monthly,annual,yearly'],
            'gateway' => ['nullable', 'string', 'in:stripe,paypal'],
            'success_url' => ['nullable', 'url', 'max:2048'],
            'cancel_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $tenantId = app(TenantContext::class)->id();
        $tenant = $tenantId === null ? null : Tenant::find($tenantId);

        if ($tenant === null) {
            abort(404, 'Household not found.');
        }

        $gatewayKey = $validated['gateway'] ?? 'stripe';
        $gateway = $this->gateways->gateway($gatewayKey);

        if (! $gateway->isConfigured()) {
            return response()->json([
                'message' => ucfirst($gatewayKey).' is not configured on this platform.',
            ], 422);
        }

        $tier = $validated['tier'];
        $period = in_array($validated['billing_period'] ?? 'monthly', ['annual', 'yearly'], true)
            ? 'annual'
            : 'monthly';
        $amountCents = PlanPricing::amountCents($tier, $period);

        if ($amountCents <= 0) {
            return response()->json([
                'message' => 'The starter plan does not require a checkout session.',
            ], 422);
        }

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'invoice_number' => Invoice::generateNumber(),
            'amount_cents' => $amountCents,
            'currency' => 'USD',
            'status' => 'pending',
            'tier' => $tier,
            'billing_period' => $period,
            'payment_method' => $gateway->key(),
            'line_items' => [[
                'description' => "FairShare {$tier} plan ({$period})",
                'quantity' => 1,
                'amount_cents' => $amountCents,
            ]],
        ]);

        try {
            $session = $gateway->createCheckout(
                $invoice,
                $validated['success_url'] ?? url('/billing?checkout=success'),
                $validated['cancel_url'] ?? url('/billing?checkout=cancelled'),
            );
        } catch (Throwable $exception) {
            $invoice->forceFill(['status' => 'failed'])->save();

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $this->audit->record('billing.checkout_session_created', [
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'gateway' => $gateway->key(),
            'mode' => $session->mode,
            'amount_cents' => $amountCents,
        ], 'info');

        return response()->json([
            'data' => [
                'invoice' => $invoice,
                'checkout' => [
                    'gateway' => $session->gateway,
                    'mode' => $session->mode,
                    'session_id' => $session->id,
                    'url' => $session->url,
                ],
            ],
        ], 201);
    }
}
