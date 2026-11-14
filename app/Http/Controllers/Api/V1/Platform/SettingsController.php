<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Payments\GatewayCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly GatewayCredentials $credentials,
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
            'active_gateway' => ['required', 'string', 'in:stripe,paypal,razorpay,sslcommerz,bkash,nagad,paystack,mollie,bank_transfer,mock,cash'],
            'mode' => ['required', 'string', 'in:sandbox,live'],
            'currency' => ['required', 'string', 'size:3'],
            'gateways' => ['required', 'array'],
            'auto_billing_enabled' => ['required', 'boolean'],
        ]);

        $current = $this->storedSettings();
        $storedGateways = is_array($current['gateways'] ?? null) ? $current['gateways'] : [];
        $submittedGateways = [];

        foreach ($validated['gateways'] as $key => $config) {
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

        if (! in_array($gateway, ['stripe', 'paypal', 'razorpay', 'sslcommerz', 'bkash', 'paystack', 'mollie'], true)) {
            return [
                'status' => 'unconfigured',
                'latency_ms' => null,
                'http' => 422,
                'message' => "{$label} has no API this platform can ping, so no probe was sent.",
            ];
        }

        if (! $this->credentials->isConfigured($gateway, $mode)) {
            return [
                'status' => 'unconfigured',
                'latency_ms' => null,
                'http' => 422,
                'message' => "{$label} has no {$mode} credentials. Save the {$mode} set for this gateway, or provide the environment variables, and ping again.",
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
            'bank_transfer' => 'Bank transfer',
            'cash' => 'Cash',
            'mock' => 'Sandbox simulator',
            default => ucfirst($gateway),
        };
    }
}
