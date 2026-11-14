<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Payments\PaymentGatewayManager;
use App\Tenancy\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * Provider callbacks, verified by signature. There is deliberately no tenant
 * context here: the provider does not carry one, and the invoice is found by
 * the reference we placed in the checkout session. Handling is idempotent.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse|RedirectResponse
    {
        // A GET is a browser return (bKash); a POST is a server callback.
        $browserReturn = $request->isMethod('get');
        $billingUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/').'/billing';

        try {
            $gateway = $this->gateways->gateway($provider);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Unknown payment provider.'], 404);
        }

        try {
            $event = $gateway->verifyWebhook($request);
            $event = $gateway->confirm($event);
        } catch (Throwable $exception) {
            if ($browserReturn) {
                return redirect()->away($billingUrl.'?checkout=failed');
            }

            return response()->json([
                'message' => 'Webhook rejected: '.$exception->getMessage(),
            ], 400);
        }

        if (! $event->paid || $event->invoiceId === null) {
            return $browserReturn
                ? redirect()->away($billingUrl.'?checkout=pending')
                : response()->json(['received' => true, 'handled' => false]);
        }

        $invoice = Invoice::find($event->invoiceId);

        if ($invoice === null) {
            return $browserReturn
                ? redirect()->away($billingUrl.'?checkout=pending')
                : response()->json(['received' => true, 'handled' => false]);
        }

        if ($invoice->status !== 'paid') {
            // The callback has no tenant context and `tenants` is FORCE ROW
            // LEVEL SECURITY, so the tier update needs the audited bypass.
            Tenancy::bypass(function () use ($invoice, $event, $gateway): void {
                $invoice->forceFill([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_method' => $gateway->key(),
                ])->save();

                Tenant::whereKey($invoice->tenant_id)->update([
                    'subscription_tier' => $invoice->tier,
                ]);

                $this->audit->record('billing.payment_confirmed', [
                    'tenant_id' => $invoice->tenant_id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'gateway' => $gateway->key(),
                    'reference' => $event->providerReference,
                    'amount_cents' => $invoice->amount_cents,
                ], 'info');
            });
        }

        return $browserReturn
            ? redirect()->away($billingUrl.'?checkout=success')
            : response()->json(['received' => true, 'handled' => true]);
    }
}
