<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PlatformSetting;
use App\Payments\GatewayCredentials;
use App\Payments\PaddleGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SettingsController extends Controller
{
    /**
     * The gateways the console can run a sandbox payment against. Cash and bank
     * transfer have no hosted checkout and Nagad has no driver, so asking for
     * either is a 404 rather than a session that can never exist.
     *
     * @var list<string>
     */
    private const SANDBOX_GATEWAYS = [
        'stripe',
        'paypal',
        'razorpay',
        'sslcommerz',
        'bkash',
        'paystack',
        'mollie',
        'paddle',
        'mock',
    ];

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly GatewayCredentials $credentials,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function getSystemSettings(): JsonResponse
    {
        $settings = PlatformSetting::get('system_settings', [
            'app_name' => 'FairShare',
            'support_email' => 'support@fairshare.app',
            'company_name' => 'FairShare Operations Inc.',
            'default_plan' => 'starter',
            'default_trial_days' => 14,
            'allow_registrations' => true,
            'require_email_verification' => true,
            'session_timeout_minutes' => 120,
            'max_members_free_tier' => 4,
            'maintenance_mode' => false,
            'maintenance_announcement' => '',
            'platform_version' => '2.4.0',
        ]);

        return response()->json(['data' => $settings]);
    }

    public function updateSystemSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'support_email' => ['required', 'email'],
            'company_name' => ['required', 'string', 'max:100'],
            'default_plan' => ['required', 'string', 'in:free,starter,family_plus,enterprise'],
            'default_trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'allow_registrations' => ['required', 'boolean'],
            'require_email_verification' => ['required', 'boolean'],
            'session_timeout_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'max_members_free_tier' => ['required', 'integer', 'min:1', 'max:20'],
            'maintenance_mode' => ['required', 'boolean'],
            'maintenance_announcement' => ['nullable', 'string', 'max:500'],
        ]);

        $current = PlatformSetting::get('system_settings', []);
        $merged = array_merge($current, $validated);

        PlatformSetting::set('system_settings', $merged);

        $this->audit->record('platform.system_settings_updated', [
            'app_name' => $validated['app_name'],
            'allow_registrations' => $validated['allow_registrations'],
            'maintenance_mode' => $validated['maintenance_mode'],
        ], 'info');

        return response()->json([
            'message' => 'System settings updated successfully.',
            'data' => $merged,
        ]);
    }

    public function getPaymentGateway(): JsonResponse
    {
        return response()->json(['data' => $this->presentSettings($this->paymentGatewaySettings())]);
    }

    /**
     * The global contract: which gateway is active, the platform currency and the
     * auto-billing flag. Credentials are edited per gateway (see updateGateway),
     * but any that arrive here are merged through the same secret protection.
     */
    public function updatePaymentGateway(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'active_gateway' => ['required', 'string', 'in:stripe,paypal,razorpay,sslcommerz,bkash,nagad,paystack,mollie,paddle,bank_transfer,mock,cash'],
            'mode' => ['required', 'string', 'in:sandbox,live'],
            'currency' => ['required', 'string', 'size:3'],
            'gateways' => ['sometimes', 'array'],
            'auto_billing_enabled' => ['required', 'boolean'],
        ]);

        $current = $this->storedSettings();
        $storedGateways = is_array($current['gateways'] ?? null) ? $current['gateways'] : [];
        $submittedGateways = $storedGateways;

        foreach (($validated['gateways'] ?? []) as $key => $config) {
            if (! is_array($config)) {
                continue;
            }

            $key = (string) $key;
            $existing = is_array($storedGateways[$key] ?? null) ? $storedGateways[$key] : [];
            $submittedGateways[$key] = $this->mergeGatewaySubmission($key, $existing, $config);
        }

        $merged = array_merge($current, $validated, ['gateways' => $submittedGateways]);

        PlatformSetting::set('payment_gateway', $merged);

        $this->audit->record('platform.payment_gateway_updated', [
            'active_gateway' => $validated['active_gateway'],
            'mode' => $validated['mode'],
            'currency' => $validated['currency'],
        ], 'info');

        return response()->json([
            'message' => 'Payment gateway settings updated.',
            'data' => $this->presentSettings($merged),
        ]);
    }

    /**
     * Save one gateway from its own card: the enabled flag, the client-facing mode
     * and both credential sets, without touching the global settings.
     */
    public function updateGateway(Request $request, string $gateway): JsonResponse
    {
        if (! in_array($gateway, $this->credentials->gatewayKeys(), true)) {
            abort(404, 'Payment gateway not found.');
        }

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'sandbox' => ['sometimes', 'boolean'],
            'credentials' => ['sometimes', 'array'],
            'credentials.sandbox' => ['sometimes', 'array'],
            'credentials.live' => ['sometimes', 'array'],
            ...$this->nonCredentialRules($gateway),
        ]);

        if ($gateway === 'paddle') {
            $credentials = is_array($validated['credentials'] ?? null) ? $validated['credentials'] : [];
            $paddleError = $this->paddleKeyValidationError($credentials)
                ?? $this->paddleDefaultLinkValidationError($validated);

            if ($paddleError !== null) {
                return response()->json(['message' => $paddleError], 422);
            }
        }

        $settings = $this->storedSettings();
        $gateways = is_array($settings['gateways'] ?? null) ? $settings['gateways'] : [];
        $existing = is_array($gateways[$gateway] ?? null) ? $gateways[$gateway] : [];

        $updated = $this->mergeGatewaySubmission($gateway, $existing, $validated);

        $gateways[$gateway] = $updated;
        $settings['gateways'] = $gateways;

        PlatformSetting::set('payment_gateway', $settings);

        $clientMode = $this->credentials->clientMode($gateway);

        $this->audit->record('platform.payment_gateway_saved', [
            'gateway' => $gateway,
            'client_mode' => $clientMode,
            'configured' => $this->credentials->isConfigured($gateway, $clientMode),
        ], 'info');

        return response()->json([
            'message' => $this->gatewayLabel($gateway).' settings saved.',
            'data' => $this->presentGateway($gateway, $updated),
        ]);
    }

    /**
     * Ping one gateway in one mode with the credentials that mode resolves to,
     * record the outcome and log it. The probe always ends in an honest status:
     * a gateway the platform cannot reach reports `unconfigured`, never success.
     */
    public function testPaymentGateway(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gateway' => ['sometimes', 'nullable', 'string', 'max:50'],
            'mode' => ['sometimes', 'nullable', 'string', 'in:sandbox,live'],
        ]);

        $gateway = $validated['gateway'] ?? 'stripe';
        $mode = $validated['mode'] ?? $this->credentials->clientMode($gateway);

        $result = $this->probeGateway($gateway, $mode);

        if (in_array($gateway, $this->credentials->gatewayKeys(), true)) {
            $this->rememberGatewayTest($gateway, $mode, $result);
        }

        $this->audit->record('platform.payment_gateway_tested', [
            'gateway' => $gateway,
            'mode' => $mode,
            'status' => $result['status'],
            'latency_ms' => $result['latency_ms'],
        ], $result['status'] === 'connected' ? 'info' : 'warning');

        return response()->json([
            'status' => $result['status'],
            'gateway' => $gateway,
            'mode' => $mode,
            'latency_ms' => $result['latency_ms'],
            'message' => $result['message'],
            'timestamp' => now()->toIso8601String(),
        ], $result['http']);
    }

    /**
     * Start a real payment against a provider's sandbox, so the console can walk
     * the whole checkout rather than ping an endpoint.
     *
     * Two guarantees hold here. The credentials are resolved for `sandbox` mode
     * explicitly, whatever the console's client-facing mode is, so a live key is
     * never touched. And the invoice is transient — force-filled and never
     * saved — so a test payment cannot appear in a household's billing history
     * or be settled by a webhook.
     */
    public function startSandboxPayment(Request $request, string $gateway): JsonResponse
    {
        if (! in_array($gateway, self::SANDBOX_GATEWAYS, true)) {
            abort(404, 'Payment gateway not found.');
        }

        [$amountCents, $currency] = $this->sandboxAmount($gateway);

        // The provider redirects the browser here after payment. The token is the
        // only handle the result page needs: it looks up the display-only invoice.
        $token = (string) Str::ulid();
        $frontend = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        // The token lives in the path: providers append their own query params to
        // the redirect URL, and a query token can be lost or mangled by that.
        $resultUrl = $frontend.'/sandbox/payment-result/'.$token.'?'.http_build_query([
            'gateway' => $gateway,
        ]);

        // The mock gateway is the platform's own simulator: it never leaves the
        // process, so there is no credential set to check and no HTTP call.
        if ($gateway === 'mock') {
            $reference = 'mock-'.Str::lower(Str::random(12));
            $invoice = $this->sandboxInvoice($gateway, $amountCents, $currency);
            $invoicePayload = $this->rememberSandboxInvoice($gateway, $invoice, $reference, $token);

            $this->auditSandboxStart('mock', $amountCents, $currency, $reference, true);

            return response()->json([
                'data' => [
                    'gateway' => 'mock',
                    'mode' => 'sandbox',
                    'reference' => $reference,
                    'url' => null,
                    'result_url' => $resultUrl,
                    'amount_cents' => $amountCents,
                    'currency' => $currency,
                    'simulated' => true,
                    'invoice' => $invoicePayload,
                ],
            ], 201);
        }

        $missing = $this->credentials->missingForProbe($gateway, 'sandbox');

        if ($missing !== []) {
            return response()->json([
                'status' => 'unconfigured',
                'message' => sprintf(
                    '%s sandbox is missing: %s. Save the sandbox set for this gateway, or provide the environment variables, and start the sandbox payment again.',
                    $this->gatewayLabel($gateway),
                    implode(', ', $missing),
                ),
            ], 422);
        }

        $successUrl = $resultUrl.'&flow=success';
        $cancelUrl = $resultUrl.'&flow=cancelled';

        $invoice = $this->sandboxInvoice($gateway, $amountCents, $currency);
        $driver = $this->gateways->gateway($gateway, 'sandbox');

        try {
            $session = $driver->createCheckout($invoice, $successUrl, $cancelUrl);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $reference = $session->reference();
        $invoicePayload = $this->rememberSandboxInvoice($gateway, $invoice, $reference, $token);

        $this->auditSandboxStart($gateway, $amountCents, $currency, $reference, false);

        $payload = [
            'gateway' => $gateway,
            'mode' => 'sandbox',
            'reference' => $reference,
            'url' => $session->url,
            'result_url' => $resultUrl,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'simulated' => false,
            'invoice' => $invoicePayload,
        ];

        // Paddle Checkout opens as a Paddle.js overlay, not as a redirect to the
        // transaction URL, so the console needs the client-side token. It is
        // public by design: it is the value the browser hands to Paddle.js.
        if ($driver instanceof PaddleGateway) {
            $payload['client_token'] = $driver->clientToken();
        }

        return response()->json(['data' => $payload], 201);
    }

    /**
     * Poll one sandbox payment. Read-only: nothing local is settled and no audit
     * row is written, because the console polls this every few seconds.
     */
    public function sandboxPaymentStatus(Request $request, string $gateway, string $reference): JsonResponse
    {
        if (! in_array($gateway, self::SANDBOX_GATEWAYS, true)) {
            abort(404, 'Payment gateway not found.');
        }

        if ($gateway === 'mock') {
            return response()->json([
                'data' => [
                    'gateway' => 'mock',
                    'mode' => 'sandbox',
                    'reference' => $reference,
                    'status' => 'paid',
                    'message' => 'The internal sandbox simulator confirms every test payment immediately.',
                    'invoice' => $this->settleSandboxInvoice($gateway, $reference, 'paid'),
                ],
            ]);
        }

        try {
            $result = $this->gateways->gateway($gateway, 'sandbox')->verifyTestPayment($reference);
        } catch (Throwable $exception) {
            // A poll must always answer with a status; the console has nothing to
            // show if this endpoint throws instead.
            $result = ['status' => 'unknown', 'message' => $exception->getMessage()];
        }

        return response()->json([
            'data' => [
                'gateway' => $gateway,
                'mode' => 'sandbox',
                'reference' => $reference,
                'status' => (string) ($result['status'] ?? 'unknown'),
                'message' => (string) ($result['message'] ?? ''),
                'invoice' => $this->settleSandboxInvoice($gateway, $reference, (string) ($result['status'] ?? 'unknown')),
            ],
        ]);
    }

    /**
     * The provider sends the browser back to the console's result page with a
     * token. This returns the display-only invoice and the provider's latest
     * status; the page shows the invoice, then returns to the gateway console.
     */
    public function sandboxPaymentResult(string $token): JsonResponse
    {
        $payload = Cache::get($this->sandboxResultKey($token));

        if (! is_array($payload)) {
            abort(404, 'Sandbox payment result not found.');
        }

        $gateway = (string) ($payload['gateway'] ?? '');
        $reference = (string) ($payload['reference'] ?? '');

        if (! in_array($gateway, self::SANDBOX_GATEWAYS, true) || $reference === '') {
            abort(404, 'Sandbox payment result not found.');
        }

        if ($gateway === 'mock') {
            $status = 'paid';
            $message = 'The internal sandbox simulator confirms every test payment immediately.';
        } else {
            try {
                $result = $this->gateways->gateway($gateway, 'sandbox')->verifyTestPayment($reference);
                $status = (string) ($result['status'] ?? 'unknown');
                $message = (string) ($result['message'] ?? '');
            } catch (Throwable $exception) {
                $status = 'unknown';
                $message = $exception->getMessage();
            }
        }

        return response()->json([
            'data' => [
                'gateway' => $gateway,
                'mode' => 'sandbox',
                'reference' => $reference,
                'status' => $status,
                'message' => $message,
                'invoice' => $this->settleSandboxInvoice($gateway, $reference, $status),
            ],
        ]);
    }

    public function getEmailConfig(): JsonResponse
    {
        $settings = PlatformSetting::get('email_config', [
            'mailer' => 'smtp',
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'postmaster@mg.fairshare.app',
            'password' => '••••••••••••••••',
            'from_address' => 'notifications@fairshare.app',
            'from_name' => 'FairShare Household Sync',
            'templates' => [
                ['id' => 'welcome', 'name' => 'Welcome & Household Onboarding', 'enabled' => true],
                ['id' => 'task_assigned', 'name' => 'Task & Errand Shared Assignment', 'enabled' => true],
                ['id' => 'voice_escalation', 'name' => 'Voice Priority Escalation Call', 'enabled' => true],
                ['id' => 'weekly_digest', 'name' => 'Weekly Shared Responsibilities Digest', 'enabled' => true],
                ['id' => 'password_reset', 'name' => 'Password & Security Verification', 'enabled' => true],
            ],
        ]);

        return response()->json(['data' => $settings]);
    }

    public function updateEmailConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mailer' => ['required', 'string', 'in:smtp,mailgun,postmark,ses,sendgrid,log'],
            'host' => ['nullable', 'string'],
            'port' => ['nullable', 'integer'],
            'encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'from_address' => ['required', 'email'],
            'from_name' => ['required', 'string'],
            'templates' => ['nullable', 'array'],
        ]);

        $current = PlatformSetting::get('email_config', []);
        // Preserve password if passed as masked bullet characters
        if (isset($validated['password']) && str_contains($validated['password'], '••')) {
            $validated['password'] = $current['password'] ?? 'secret';
        }

        $merged = array_merge($current, $validated);
        PlatformSetting::set('email_config', $merged);

        $this->audit->record('platform.email_config_updated', [
            'mailer' => $validated['mailer'],
            'from_address' => $validated['from_address'],
        ], 'info');

        return response()->json([
            'message' => 'Email configuration updated.',
            'data' => $merged,
        ]);
    }

    public function testEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'email'],
        ]);

        $email = $validated['recipient'];
        $fromName = config('mail.from.name', 'FairShare Platform');

        try {
            Mail::raw("Hello from {$fromName}!\n\nThis is a verified test email sent by Platform Administration to confirm email delivery pipeline health.", function ($message) use ($email) {
                $message->to($email)
                    ->subject('FairShare Platform — Email Delivery Verification');
            });

            $this->audit->record('platform.email_test_sent', ['recipient' => $email], 'info');

            return response()->json([
                'success' => true,
                'message' => "Test email dispatched successfully to {$email}.",
                'dispatched_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to deliver test email: {$e->getMessage()}",
            ], 422);
        }
    }

    /**
     * The gateways the console shows before anything has been saved.
     *
     * @return array<string, array<string, mixed>>
     */
    private function gatewayDefaults(): array
    {
        return [
            'stripe' => [
                'enabled' => true,
                'sandbox' => true,
                'publishable_key' => 'pk_test_fairshare_sandbox_mock51928374',
                'secret_key' => 'sk_test_••••••••••••••••••••••••••••••••',
                'webhook_secret' => 'whsec_••••••••••••••••••••••••••••',
            ],
            'paypal' => [
                'enabled' => false,
                'sandbox' => true,
                'client_id' => 'sb-fairshare-mock-id',
                'client_secret' => '••••••••••••••••',
                'webhook_id' => 'WH-••••••••••••••••',
            ],
            'razorpay' => [
                'enabled' => false,
                'sandbox' => true,
                'key_id' => 'rzp_test_fairshare',
                'key_secret' => '••••••••••••••••',
                'webhook_secret' => '••••••••••••••••',
            ],
            'sslcommerz' => [
                'enabled' => false,
                'sandbox' => true,
                'store_id' => 'fairshare_sandbox',
                'store_passwd' => '••••••••••••••••',
            ],
            'bkash' => [
                'enabled' => false,
                'sandbox' => true,
                'app_key' => 'bkash_sandbox_app_key',
                'app_secret' => '••••••••••••••••',
                'username' => 'sandbox_user',
                'password' => '••••••••••••••••',
            ],
            'nagad' => [
                'enabled' => false,
                'sandbox' => true,
                'merchant_id' => 'NAGAD_SANDBOX_01',
                'public_key' => '••••••••••••••••',
                'private_key' => '••••••••••••••••',
            ],
            'paystack' => [
                'enabled' => false,
                'sandbox' => true,
                'public_key' => 'pk_test_••••••••••••••••',
                'secret_key' => 'sk_test_••••••••••••••••',
            ],
            'mollie' => [
                'enabled' => false,
                'sandbox' => true,
                'api_key' => 'test_••••••••••••••••',
            ],
            'paddle' => [
                'enabled' => false,
                'sandbox' => true,
                'api_key' => '',
                'webhook_secret' => '',
                'price_id' => '',
                'test_price_id' => '',
                'client_token' => '',
                'default_payment_link' => '',
            ],
            'bank_transfer' => [
                'enabled' => false,
                'bank_name' => 'City Bank / Chase',
                'account_name' => 'FairShare Operations Inc.',
                'account_number' => '••••••••1234',
                'routing_code' => '021000021',
                'swift_bic' => 'CHASUS33',
                'instructions' => 'Please reference your Invoice Number in the wire transfer description.',
            ],
            'cash' => [
                'enabled' => true,
                'instructions' => 'Cash payment upon in-person settlement or physical invoice collection.',
                'auto_mark_paid' => false,
            ],
            'mock' => [
                'enabled' => true,
                'sandbox' => true,
                'simulate_failure' => false,
                'latency_ms' => 120,
            ],
        ];
    }

    /**
     * What is actually stored, with no defaults mixed in.
     *
     * @return array<string, mixed>
     */
    private function storedSettings(): array
    {
        $settings = PlatformSetting::get('payment_gateway', []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * The stored settings with the global defaults and every known gateway filled
     * in, so the console always receives a complete shape.
     *
     * @return array<string, mixed>
     */
    private function paymentGatewaySettings(): array
    {
        $settings = array_merge([
            'active_gateway' => 'stripe',
            'mode' => 'sandbox',
            'currency' => 'USD',
            'webhook_url' => '/api/v1/webhooks/payment',
            'auto_billing_enabled' => true,
        ], $this->storedSettings());

        $gateways = is_array($settings['gateways'] ?? null) ? $settings['gateways'] : [];

        foreach ($this->gatewayDefaults() as $key => $default) {
            $stored = $gateways[$key] ?? [];
            $gateways[$key] = array_merge($default, is_array($stored) ? $stored : []);
        }

        $settings['gateways'] = $gateways;

        return $settings;
    }

    /**
     * A settings payload that is safe to return: every gateway shaped by
     * presentGateway(), which masks every secret.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function presentSettings(array $settings): array
    {
        $gateways = is_array($settings['gateways'] ?? null) ? $settings['gateways'] : [];
        $presented = [];

        foreach ($gateways as $key => $config) {
            $presented[$key] = $this->presentGateway((string) $key, is_array($config) ? $config : []);
        }

        $settings['gateways'] = $presented;

        return $settings;
    }

    /**
     * One gateway as the console sees it: the legacy flat fields for backwards
     * compatibility, both credential sets, and the last ping per mode. Every
     * secret is replaced by the mask sentinel.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function presentGateway(string $gateway, array $config): array
    {
        if (! in_array($gateway, $this->credentials->gatewayKeys(), true)) {
            return $this->credentials->mask($config);
        }

        $flat = [];

        foreach ($this->credentials->fields($gateway) as $field) {
            if (array_key_exists($field, $config)) {
                $flat[$field] = $config[$field];
            }
        }

        $sets = $this->credentials->storedSets($gateway, $config);
        $lastTest = is_array($config['last_test'] ?? null) ? $config['last_test'] : [];

        $presented = [
            'enabled' => filter_var($config['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        if (array_key_exists('sandbox', $config)) {
            $presented['sandbox'] = filter_var($config['sandbox'], FILTER_VALIDATE_BOOLEAN);
        }

        return array_merge($presented, $this->credentials->mask($flat), [
            'credentials' => [
                'sandbox' => $this->credentials->mask($sets['sandbox']),
                'live' => $this->credentials->mask($sets['live']),
            ],
            'last_test' => [
                'sandbox' => is_array($lastTest['sandbox'] ?? null) ? $lastTest['sandbox'] : null,
                'live' => is_array($lastTest['live'] ?? null) ? $lastTest['live'] : null,
            ],
            // Whether each environment has every required credential, so the
            // console can show a check mark without re-implementing the rules.
            'configured' => [
                'sandbox' => $this->isGatewayConfigured($gateway, 'sandbox'),
                'live' => $this->isGatewayConfigured($gateway, 'live'),
            ],
        ]);
    }

    /**
     * Overlay one submitted gateway config on what is stored for it.
     *
     * Secrets — in either credential set, or as a legacy flat field — keep their
     * stored value when the submission carries the mask sentinel or nothing at
     * all, so a screen that only round-trips masks can never erase a credential.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    private function mergeGatewaySubmission(string $gateway, array $existing, array $submitted): array
    {
        $updated = $existing;
        $fields = $this->credentials->fields($gateway);
        $secretFields = $this->credentials->secretFields($gateway);

        if (array_key_exists('enabled', $submitted)) {
            $updated['enabled'] = filter_var($submitted['enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('sandbox', $submitted)) {
            $updated['sandbox'] = filter_var($submitted['sandbox'], FILTER_VALIDATE_BOOLEAN);
        }

        if (is_array($submitted['credentials'] ?? null)) {
            $storedSets = $this->credentials->storedSets($gateway, $existing);
            $mergedSets = [];

            foreach (['sandbox', 'live'] as $mode) {
                $submittedSet = $submitted['credentials'][$mode] ?? [];
                $submittedSet = is_array($submittedSet) ? $submittedSet : [];

                if ($fields !== []) {
                    $submittedSet = array_intersect_key($submittedSet, array_flip($fields));
                }

                $mergedSets[$mode] = $this->credentials->mergeSubmitted($storedSets[$mode], $submittedSet);
            }

            $updated['credentials'] = $mergedSets;
        }

        $flat = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $submitted) && ! is_array($submitted[$field])) {
                $flat[$field] = $submitted[$field];
            }
        }

        $updated = array_merge($updated, $this->credentials->mergeSubmitted(
            array_intersect_key($existing, array_flip($secretFields)),
            array_intersect_key($flat, array_flip($secretFields)),
        ));

        foreach (array_diff_key($flat, array_flip($secretFields)) as $field => $value) {
            $updated[$field] = $value;
        }

        return $updated;
    }

    /**
     * Rules for the flat, non-credential fields of the offline gateways.
     *
     * @return array<string, list<string>>
     */
    private function nonCredentialRules(string $gateway): array
    {
        return match ($gateway) {
            'cash' => [
                'instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
                'auto_mark_paid' => ['sometimes', 'boolean'],
            ],
            'bank_transfer' => [
                'bank_name' => ['sometimes', 'nullable', 'string', 'max:120'],
                'account_name' => ['sometimes', 'nullable', 'string', 'max:120'],
                'account_number' => ['sometimes', 'nullable', 'string', 'max:64'],
                'routing_code' => ['sometimes', 'nullable', 'string', 'max:64'],
                'swift_bic' => ['sometimes', 'nullable', 'string', 'max:32'],
                'instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
            ],
            'mock' => [
                'simulate_failure' => ['sometimes', 'boolean'],
                'latency_ms' => ['sometimes', 'integer', 'min:0', 'max:60000'],
            ],
            default => [],
        };
    }

    /**
     * Probe one gateway with the credentials resolved for the requested mode.
     *
     * @return array{status: string, message: string, latency_ms: int|null, http: int}
     */
    private function probeGateway(string $gateway, string $mode): array
    {
        $label = $this->gatewayLabel($gateway);

        if (in_array($gateway, ['cash', 'mock', 'bank_transfer'], true)) {
            return match ($gateway) {
                'cash' => [
                    'status' => 'connected',
                    'latency_ms' => 0,
                    'http' => 200,
                    'message' => 'Cash payment and offline collection workflow is active and ready.',
                ],
                'mock' => [
                    'status' => 'connected',
                    'latency_ms' => 15,
                    'http' => 200,
                    'message' => 'Internal Sandbox Payment Simulator is active. Test card 4242-4242-4242-4242 ready.',
                ],
                default => [
                    'status' => 'connected',
                    'latency_ms' => 0,
                    'http' => 200,
                    'message' => 'Bank Wire / Direct Transfer details configured for offline invoice settlement.',
                ],
            };
        }

        if (! in_array($gateway, ['stripe', 'paypal', 'razorpay', 'sslcommerz', 'bkash', 'paystack', 'mollie', 'paddle'], true)) {
            return [
                'status' => 'unconfigured',
                'latency_ms' => null,
                'http' => 422,
                'message' => "{$label} has no API this platform can ping, so no probe was sent.",
            ];
        }

        $missing = $this->credentials->missingForProbe($gateway, $mode);

        if ($missing !== []) {
            return [
                'status' => 'unconfigured',
                'latency_ms' => null,
                'http' => 422,
                'message' => sprintf(
                    '%s %s is missing: %s. Save the %s set for this gateway, or provide the environment variables, and ping again.',
                    $label,
                    $mode,
                    implode(', ', $missing),
                    $mode,
                ),
            ];
        }

        $credentials = $this->credentials->resolve($gateway, $mode);

        return match ($gateway) {
            'stripe' => $this->probeStripe($credentials, $mode),
            'paypal' => $this->probePayPal($credentials, $mode),
            'razorpay' => $this->probeRazorpay($credentials, $mode),
            'sslcommerz' => $this->probeSslCommerz(),
            'bkash' => $this->probeBkash($credentials, $mode),
            'paystack' => $this->probePaystack($credentials, $mode),
            'mollie' => $this->probeMollie($credentials, $mode),
            'paddle' => $this->probePaddle($credentials, $mode),
            default => [
                'status' => 'unconfigured',
                'latency_ms' => null,
                'http' => 422,
                'message' => "{$label} has no API this platform can ping, so no probe was sent.",
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{status: string, message: string, latency_ms: int|null, http: int}
     */
    private function probeStripe(array $credentials, string $mode): array
    {
        $start = microtime(true);

        try {
            $response = Http::withToken((string) $credentials['secret_key'])
                ->acceptJson()
                ->timeout(10)
                ->get('https://api.stripe.com/v1/balance');
        } catch (Throwable $exception) {
            return $this->probeUnreachable('Stripe', $exception);
        }

        $latencyMs = $this->elapsedMs($start);

        if ($response->failed()) {
            return $this->probeRejected($latencyMs, 'Stripe', $response->json('error.message'), $response->status());
        }

        return [
            'status' => 'connected',
            'latency_ms' => $latencyMs,
            'http' => 200,
            'message' => "Stripe API reachable with the configured {$mode} secret key.",
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{status: string, message: string, latency_ms: int|null, http: int}
     */
    private function probePayPal(array $credentials, string $mode): array
    {
        $base = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
        $start = microtime(true);

        try {
            $response = Http::withBasicAuth((string) $credentials['client_id'], (string) $credentials['client_secret'])
                ->asForm()
                ->acceptJson()
                ->timeout(10)
                ->post($base.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (Throwable $exception) {
            return $this->probeUnreachable('PayPal', $exception);
        }

        $latencyMs = $this->elapsedMs($start);

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            return $this->probeRejected($latencyMs, 'PayPal', $response->json('error_description'), $response->status());
        }

        return [
            'status' => 'connected',
            'latency_ms' => $latencyMs,
            'http' => 200,
            'message' => "PayPal OAuth token issued in {$mode} mode.",
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{status: string, message: string, latency_ms: int|null, http: int}
     */
    private function probeRazorpay(array $credentials, string $mode): array
    {
        $start = microtime(true);

        try {
            $response = Http::withBasicAuth((string) $credentials['key_id'], (string) $credentials['key_secret'])
                ->acceptJson()
                ->timeout(10)
                ->get('https://api.razorpay.com/v1/orders', ['count' => 1]);
        } catch (Throwable $exception) {
            return $this->probeUnreachable('Razorpay', $exception);
        }

        $latencyMs = $this->elapsedMs($start);

        if ($response->failed()) {
            return $this->probeRejected($latencyMs, 'Razorpay', $response->json('error.description'), $response->status());
        }

        return [
            'status' => 'connected',
            'latency_ms' => $latencyMs,
            'http' => 200,
            'message' => "Razorpay API reachable with the configured {$mode} keys.",
        ];
    }

    /**
     * SSLCommerz publishes no ping endpoint, so presence of the credentials is all
     * that can honestly be reported; the first session validates them.
     *
     * @return array{status: string, message: string, latency_ms: int|null, http: int}
     */
    private function probeSslCommerz(): array
    {
        return [
            'status' => 'connected',
            'latency_ms' => 0,
            'http' => 200,
            'message' => 'SSLCommerz credentials are present. SSLCommerz has no ping endpoint; the first session validates them.',
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{status: string, message: string, latency_ms: int|null, http: int}
     */
    private function probeBkash(array $credentials, string $mode): array
    {
        $base = $mode === 'live'
            ? 'https://tokenized.pay.bka.sh/v1.2.0-beta'
            : 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';
        $start = microtime(true);

        try {
            $response = Http::withHeaders([
                'username' => (string) $credentials['username'],
                'password' => (string) $credentials['password'],
            ])->acceptJson()->asJson()->timeout(10)->post(
                $base.'/tokenized/checkout/token/grant',
                [
                    'app_key' => (string) $credentials['app_key'],
                    'app_secret' => (string) $credentials['app_secret'],
                ],
            );
        } catch (Throwable $exception) {
            return $this->probeUnreachable('bKash', $exception);
        }

        $latencyMs = $this->elapsedMs($start);

        if ($response->failed() || ! is_string($response->json('id_token'))) {
            return $this->probeRejected($latencyMs, 'bKash', $response->json('statusMessage'), $response->status());
        }

        return [
            'status' => 'connected',
            'latency_ms' => $latencyMs,
            'http' => 200,
            'message' => "bKash token issued in {$mode} mode.",
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{status: string, message: string, latency_ms: int|null, http: int}
     */
    private function probePaystack(array $credentials, string $mode): array
    {
        $start = microtime(true);

        try {
            $response = Http::withToken((string) $credentials['secret_key'])
                ->acceptJson()
                ->timeout(10)
                ->get('https://api.paystack.co/transaction', ['perPage' => 1]);
        } catch (Throwable $exception) {
            return $this->probeUnreachable('Paystack', $exception);
        }

        $latencyMs = $this->elapsedMs($start);

        if ($response->failed()) {
            return $this->probeRejected($latencyMs, 'Paystack', $response->json('message'), $response->status());
        }

        return [
            'status' => 'connected',
            'latency_ms' => $latencyMs,
            'http' => 200,
            'message' => "Paystack API reachable with the configured {$mode} key.",
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{status: string, message: string, latency_ms: int|null, http: int}
     */
    private function probeMollie(array $credentials, string $mode): array
    {
        $start = microtime(true);

        try {
            $response = Http::withToken((string) $credentials['api_key'])
                ->acceptJson()
                ->timeout(10)
                ->get('https://api.mollie.com/v2/payments', ['limit' => 1]);
        } catch (Throwable $exception) {
            return $this->probeUnreachable('Mollie', $exception);
        }

        $latencyMs = $this->elapsedMs($start);

        if ($response->failed()) {
            return $this->probeRejected($latencyMs, 'Mollie', $response->json('detail'), $response->status());
        }

        return [
            'status' => 'connected',
            'latency_ms' => $latencyMs,
            'http' => 200,
            'message' => "Mollie API reachable with the configured {$mode} key.",
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{status: string, message: string, latency_ms: int|null, http: int}
     */
    private function probePaddle(array $credentials, string $mode): array
    {
        $base = $mode === 'live'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';
        $rawKey = (string) ($credentials['api_key'] ?? '');
        $apiKey = PaddleGateway::normaliseApiKey($rawKey, $mode);

        if ($apiKey === null || (! str_starts_with($apiKey, 'pdl_sdbx_apikey_') && ! str_starts_with($apiKey, 'pdl_live_apikey_'))) {
            return [
                'status' => 'unconfigured',
                'latency_ms' => null,
                'http' => 422,
                'message' => 'Paddle API key is malformed: it must start with pdl_sdbx_apikey_ (sandbox) or pdl_live_apikey_ (live).',
            ];
        }

        if (strlen($apiKey) < 60) {
            return [
                'status' => 'unconfigured',
                'latency_ms' => null,
                'http' => 422,
                'message' => sprintf(
                    'The saved Paddle API key is incomplete (%d characters — public key ID only). Paddle keys are 69 characters and the secret is shown once at creation. Create a new Paddle API key and paste the whole value.',
                    strlen(trim($rawKey)),
                ),
            ];
        }

        $start = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(10)
                ->get($base.'/transactions', ['per_page' => 1]);
        } catch (Throwable $exception) {
            return $this->probeUnreachable('Paddle', $exception);
        }

        $latencyMs = $this->elapsedMs($start);

        if ($response->failed()) {
            return $this->probeRejected($latencyMs, 'Paddle', $response->json('error.detail'), $response->status());
        }

        return [
            'status' => 'connected',
            'latency_ms' => $latencyMs,
            'http' => 200,
            'message' => "Paddle API reachable with the configured {$mode} API key.",
        ];
    }

    /** @return array{status: string, message: string, latency_ms: int|null, http: int} */
    private function probeUnreachable(string $label, Throwable $exception): array
    {
        return [
            'status' => 'error',
            'latency_ms' => null,
            'http' => 502,
            'message' => "Could not reach {$label}: ".$exception->getMessage(),
        ];
    }

    /** @return array{status: string, message: string, latency_ms: int|null, http: int} */
    private function probeRejected(int $latencyMs, string $label, mixed $detail, int $status): array
    {
        return [
            'status' => 'error',
            'latency_ms' => $latencyMs,
            'http' => 502,
            'message' => "{$label} rejected the credentials: "
                .(is_string($detail) ? $detail : 'HTTP '.$status),
        ];
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    /**
     * Record a ping so the console can show the last result per mode.
     *
     * @param  array{status: string, message: string, latency_ms: int|null, http: int}  $result
     */
    private function rememberGatewayTest(string $gateway, string $mode, array $result): void
    {
        $settings = $this->storedSettings();
        $gateways = is_array($settings['gateways'] ?? null) ? $settings['gateways'] : [];
        $config = is_array($gateways[$gateway] ?? null) ? $gateways[$gateway] : [];
        $lastTest = is_array($config['last_test'] ?? null) ? $config['last_test'] : [];

        $lastTest[$mode] = [
            'status' => $result['status'],
            'latency_ms' => $result['latency_ms'],
            'message' => $result['message'],
            'at' => now()->toIso8601String(),
        ];

        $config['last_test'] = $lastTest;
        $gateways[$gateway] = $config;
        $settings['gateways'] = $gateways;

        PlatformSetting::set('payment_gateway', $settings);
    }

    private function gatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'razorpay' => 'Razorpay',
            'sslcommerz' => 'SSLCommerz',
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'paystack' => 'Paystack',
            'mollie' => 'Mollie',
            'paddle' => 'Paddle',
            'bank_transfer' => 'Bank transfer',
            'cash' => 'Cash',
            'mock' => 'Sandbox simulator',
            default => ucfirst($gateway),
        };
    }

    /**
     * What one sandbox payment costs, in minor units. Each provider is given the
     * smallest amount it will actually accept, in its own currency — this is the
     * gateway's test amount, never a plan price.
     *
     * @return array{0: int, 1: string}
     */
    private function sandboxAmount(string $gateway): array
    {
        return match ($gateway) {
            'razorpay' => [100, 'INR'],
            'sslcommerz', 'bkash' => [1000, 'BDT'],
            'paystack' => [10000, 'NGN'],
            'mollie' => [100, 'EUR'],
            'paddle' => [100, 'USD'],
            default => [100, 'USD'],
        };
    }

    /**
     * A display-only invoice for a sandbox payment. It lives in the cache for two
     * hours so every poll can show the same number and amount; it is deliberately
     * never written to the billing ledger.
     *
     * @return array<string, mixed>
     */
    private function rememberSandboxInvoice(string $gateway, Invoice $invoice, string $reference, string $token): array
    {
        $payload = [
            'invoice_number' => (string) $invoice->invoice_number,
            'amount_cents' => (int) $invoice->amount_cents,
            'currency' => (string) $invoice->currency,
            'tier' => (string) $invoice->tier,
            'billing_period' => (string) $invoice->billing_period,
            'payment_method' => $gateway,
            'gateway' => $gateway,
            'status' => 'pending',
            'issued_at' => now()->toIso8601String(),
            'paid_at' => null,
            'reference' => $reference,
            'token' => $token,
            'sandbox' => true,
        ];

        Cache::put($this->sandboxInvoiceKey($gateway, $reference), $payload, now()->addHours(2));
        Cache::put($this->sandboxResultKey($token), $payload, now()->addHours(2));

        return $payload;
    }

    /**
     * Move the display-only invoice with the provider status. `paid_at` is stamped
     * once and cached, so repeated polls show the same time.
     *
     * @return array<string, mixed>|null
     */
    private function settleSandboxInvoice(string $gateway, string $reference, string $status): ?array
    {
        $key = $this->sandboxInvoiceKey($gateway, $reference);
        $payload = Cache::get($key);

        if (! is_array($payload)) {
            return null;
        }

        if ($status === 'paid' && ($payload['status'] ?? null) !== 'paid') {
            $payload['status'] = 'paid';
            $payload['paid_at'] = now()->toIso8601String();
        } elseif ($status === 'failed' && ($payload['status'] ?? null) !== 'failed') {
            $payload['status'] = 'failed';
        }

        Cache::put($key, $payload, now()->addHours(2));

        if (is_string($payload['token'] ?? null)) {
            Cache::put($this->sandboxResultKey($payload['token']), $payload, now()->addHours(2));
        }

        return $payload;
    }

    /**
     * Probe fields present, plus Paddle's full 69-character key. A pasted
     * `apikey_…` value is only the public key ID and cannot authenticate.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function paddleKeyValidationError(array $credentials): ?string
    {
        foreach (['sandbox', 'live'] as $mode) {
            $set = $credentials[$mode] ?? null;

            if (! is_array($set)) {
                continue;
            }

            $key = $set['api_key'] ?? null;

            if (! is_string($key) || trim($key) === '' || str_contains($key, '•')) {
                continue; // empty or masked: keep the stored value
            }

            if (strlen((string) PaddleGateway::normaliseApiKey($key, $mode)) >= 60) {
                continue;
            }

            return sprintf(
                'That Paddle API key is incomplete (%d characters). It is the public key ID only — the secret is shown once when you create the key. Create a new Paddle API key and paste the whole 69-character value.',
                strlen(trim($key)),
            );
        }

        return null;
    }

    /**
     * Paddle rejects a default payment link that is not https — including
     * localhost — so saving one only produces a checkout that fails later.
     * The link is checked in both credential sets and as a legacy flat field.
     *
     * @param  array<string, mixed>  $submitted
     */
    private function paddleDefaultLinkValidationError(array $submitted): ?string
    {
        $candidates = [$submitted['default_payment_link'] ?? null];
        $credentials = $submitted['credentials'] ?? null;

        if (is_array($credentials)) {
            foreach (['sandbox', 'live'] as $mode) {
                $set = $credentials[$mode] ?? null;

                if (is_array($set)) {
                    $candidates[] = $set['default_payment_link'] ?? null;
                }
            }
        }

        foreach ($candidates as $link) {
            if (! is_string($link) || trim($link) === '' || str_contains($link, '•')) {
                continue; // empty or masked: keep the stored value
            }

            if (str_starts_with(trim($link), 'https://')) {
                continue;
            }

            return 'The Paddle default payment link must start with https:// — http and localhost URLs are rejected. '
                .'The sandbox checkout opens as a Paddle.js overlay, so this page is never visited; any https URL works in sandbox.';
        }

        return null;
    }

    private function isGatewayConfigured(string $gateway, string $mode): bool
    {
        if ($this->credentials->missingForProbe($gateway, $mode) !== []) {
            return false;
        }

        if ($gateway === 'paddle') {
            $resolved = $this->credentials->resolve($gateway, $mode);
            $key = (string) ($resolved['api_key'] ?? '');
            $clientToken = trim((string) ($resolved['client_token'] ?? ''));

            // The full key authenticates and the client token opens the overlay:
            // without either the card cannot actually pay.
            return strlen((string) PaddleGateway::normaliseApiKey($key, $mode)) >= 60
                && $clientToken !== '';
        }

        return true;
    }

    private function sandboxInvoiceKey(string $gateway, string $reference): string
    {
        return 'sandbox-invoice:'.$gateway.':'.$reference;
    }

    private function sandboxResultKey(string $token): string
    {
        return 'sandbox-invoice:token:'.$token;
    }

    /**
     * The invoice a sandbox payment is made against: an unsaved model, so it can
     * never reach the billing ledger. The `tenant` relation is set explicitly so
     * a driver that loads it does not query for a household this invoice does not
     * have.
     */
    private function sandboxInvoice(string $gateway, int $amountCents, string $currency): Invoice
    {
        $invoice = new Invoice;

        $invoice->forceFill([
            // Providers such as Stripe reject an empty client_reference_id, so the
            // unsaved test invoice carries a synthetic id. It is never persisted.
            'id' => random_int(900_000_000, 999_999_999),
            'tenant_id' => 0,
            'invoice_number' => 'TEST-'.strtoupper($gateway).'-'.Str::upper(Str::random(8)),
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'tier' => 'family',
            'billing_period' => 'monthly',
            'status' => 'pending',
            'payment_method' => $gateway,
        ]);

        $invoice->setRelation('tenant', null);

        return $invoice;
    }

    /** Log a sandbox payment start — the reference and amount only, never a secret. */
    private function auditSandboxStart(
        string $gateway,
        int $amountCents,
        string $currency,
        string $reference,
        bool $simulated,
    ): void {
        $this->audit->record('platform.payment_gateway_sandbox_payment_started', [
            'gateway' => $gateway,
            'mode' => 'sandbox',
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'reference' => $reference,
            'simulated' => $simulated,
        ], 'info');
    }
}
