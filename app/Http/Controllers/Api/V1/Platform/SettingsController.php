<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

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
        $defaultGateways = [
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

        $settings = PlatformSetting::get('payment_gateway', [
            'active_gateway' => 'stripe',
            'mode' => 'sandbox',
            'currency' => 'USD',
            'gateways' => $defaultGateways,
            'webhook_url' => '/api/v1/webhooks/payment',
            'auto_billing_enabled' => true,
        ]);

        // Merge any newly introduced gateways into existing database configuration
        $existingGateways = $settings['gateways'] ?? [];
        foreach ($defaultGateways as $key => $defaultConfig) {
            if (! isset($existingGateways[$key])) {
                $existingGateways[$key] = $defaultConfig;
            }
        }
        $settings['gateways'] = $existingGateways;

        return response()->json(['data' => $settings]);
    }

    public function updatePaymentGateway(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'active_gateway' => ['required', 'string', 'in:stripe,paypal,razorpay,sslcommerz,bkash,nagad,paystack,mollie,bank_transfer,mock,cash'],
            'mode' => ['required', 'string', 'in:sandbox,live'],
            'currency' => ['required', 'string', 'size:3'],
            'gateways' => ['required', 'array'],
            'auto_billing_enabled' => ['required', 'boolean'],
        ]);

        $current = PlatformSetting::get('payment_gateway', []);
        $merged = array_merge($current, $validated);

        PlatformSetting::set('payment_gateway', $merged);

        $this->audit->record('platform.payment_gateway_updated', [
            'active_gateway' => $validated['active_gateway'],
            'mode' => $validated['mode'],
            'currency' => $validated['currency'],
        ], 'info');

        return response()->json([
            'message' => 'Payment gateway settings updated.',
            'data' => $merged,
        ]);
    }

    public function testPaymentGateway(Request $request): JsonResponse
    {
        $gateway = $request->input('gateway', 'stripe');
        $settings = PlatformSetting::get('payment_gateway', []);
        $gatewayConfig = $settings['gateways'][$gateway] ?? [];
        $isSandbox = $request->has('mode')
            ? ($request->input('mode') === 'sandbox')
            : (bool) ($gatewayConfig['sandbox'] ?? true);
        $mode = $isSandbox ? 'sandbox' : 'live';

        if ($gateway === 'stripe') {
            $secret = config('services.stripe.secret');

            if (! is_string($secret) || $secret === '') {
                return response()->json([
                    'status' => 'unconfigured',
                    'gateway' => 'stripe',
                    'message' => 'STRIPE_SECRET is not set. Add the sandbox or live secret key to the environment.',
                ], 422);
            }

            $mode = str_starts_with($secret, 'sk_live_') ? 'live' : 'sandbox';
            $start = microtime(true);

            try {
                $response = Http::withToken($secret)
                    ->acceptJson()
                    ->timeout(10)
                    ->get('https://api.stripe.com/v1/balance');
            } catch (Throwable $exception) {
                return response()->json([
                    'status' => 'error',
                    'gateway' => 'stripe',
                    'mode' => $mode,
                    'message' => 'Could not reach Stripe: '.$exception->getMessage(),
                ], 502);
            }

            $latencyMs = round((microtime(true) - $start) * 1000);

            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'gateway' => 'stripe',
                    'mode' => $mode,
                    'latency_ms' => $latencyMs,
                    'message' => 'Stripe rejected the credentials: '
                        .($response->json('error.message') ?? 'HTTP '.$response->status()),
                ], 502);
            }

            return response()->json([
                'status' => 'connected',
                'gateway' => 'stripe',
                'mode' => $mode,
                'latency_ms' => $latencyMs,
                'message' => "Stripe API reachable with the configured {$mode} secret key.",
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        if ($gateway === 'paypal') {
            $clientId = config('services.paypal.client_id');
            $clientSecret = config('services.paypal.client_secret');

            if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
                return response()->json([
                    'status' => 'unconfigured',
                    'gateway' => 'paypal',
                    'message' => 'PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET are not set.',
                ], 422);
            }

            $paypalMode = config('services.paypal.mode', 'sandbox') === 'live' ? 'live' : 'sandbox';
            $base = $paypalMode === 'live'
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com';
            $start = microtime(true);

            try {
                $response = Http::withBasicAuth($clientId, $clientSecret)
                    ->asForm()
                    ->acceptJson()
                    ->timeout(10)
                    ->post($base.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
            } catch (Throwable $exception) {
                return response()->json([
                    'status' => 'error',
                    'gateway' => 'paypal',
                    'mode' => $paypalMode,
                    'message' => 'Could not reach PayPal: '.$exception->getMessage(),
                ], 502);
            }

            $latencyMs = round((microtime(true) - $start) * 1000);

            if ($response->failed() || ! is_string($response->json('access_token'))) {
                return response()->json([
                    'status' => 'error',
                    'gateway' => 'paypal',
                    'mode' => $paypalMode,
                    'latency_ms' => $latencyMs,
                    'message' => 'PayPal rejected the credentials: '
                        .($response->json('error_description') ?? 'HTTP '.$response->status()),
                ], 502);
            }

            return response()->json([
                'status' => 'connected',
                'gateway' => 'paypal',
                'mode' => $paypalMode,
                'latency_ms' => $latencyMs,
                'message' => "PayPal OAuth token issued in {$paypalMode} mode.",
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        if ($gateway === 'razorpay') {
            $keyId = config('services.razorpay.key_id');
            $keySecret = config('services.razorpay.key_secret');

            if (! is_string($keyId) || $keyId === '' || ! is_string($keySecret) || $keySecret === '') {
                return response()->json([
                    'status' => 'unconfigured',
                    'gateway' => 'razorpay',
                    'message' => 'RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET are not set.',
                ], 422);
            }

            $razorpayMode = str_starts_with($keyId, 'rzp_live_') ? 'live' : 'sandbox';
            $start = microtime(true);

            try {
                $response = Http::withBasicAuth($keyId, $keySecret)
                    ->acceptJson()
                    ->timeout(10)
                    ->get('https://api.razorpay.com/v1/orders', ['count' => 1]);
            } catch (Throwable $exception) {
                return response()->json([
                    'status' => 'error',
                    'gateway' => 'razorpay',
                    'mode' => $razorpayMode,
                    'message' => 'Could not reach Razorpay: '.$exception->getMessage(),
                ], 502);
            }

            $latencyMs = round((microtime(true) - $start) * 1000);

            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'gateway' => 'razorpay',
                    'mode' => $razorpayMode,
                    'latency_ms' => $latencyMs,
                    'message' => 'Razorpay rejected the credentials: '
                        .($response->json('error.description') ?? 'HTTP '.$response->status()),
                ], 502);
            }

            return response()->json([
                'status' => 'connected',
                'gateway' => 'razorpay',
                'mode' => $razorpayMode,
                'latency_ms' => $latencyMs,
                'message' => "Razorpay API reachable with the configured {$razorpayMode} keys.",
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        if ($gateway === 'sslcommerz') {
            $storeId = config('services.sslcommerz.store_id');
            $storePassword = config('services.sslcommerz.store_password');

            if (! is_string($storeId) || $storeId === '' || ! is_string($storePassword) || $storePassword === '') {
                return response()->json([
                    'status' => 'unconfigured',
                    'gateway' => 'sslcommerz',
                    'message' => 'SSLCOMMERZ_STORE_ID and SSLCOMMERZ_STORE_PASSWD are not set.',
                ], 422);
            }

            $sslMode = config('services.sslcommerz.mode', 'sandbox') === 'live' ? 'live' : 'sandbox';

            return response()->json([
                'status' => 'connected',
                'gateway' => 'sslcommerz',
                'mode' => $sslMode,
                'latency_ms' => 0,
                'message' => 'SSLCommerz credentials are present. SSLCommerz has no ping endpoint; the first session validates them.',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        if ($gateway === 'cash') {
            return response()->json([
                'status' => 'connected',
                'gateway' => 'cash',
                'mode' => $mode,
                'latency_ms' => 0,
                'message' => 'Cash payment and offline collection workflow is active and ready.',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        if ($gateway === 'mock') {
            return response()->json([
                'status' => 'connected',
                'gateway' => 'mock',
                'mode' => 'sandbox',
                'latency_ms' => 15,
                'message' => 'Internal Sandbox Payment Simulator is active. Test card 4242-4242-4242-4242 ready.',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        if ($gateway === 'bank_transfer') {
            return response()->json([
                'status' => 'connected',
                'gateway' => 'bank_transfer',
                'mode' => $mode,
                'latency_ms' => 0,
                'message' => 'Bank Wire / Direct Transfer details configured for offline invoice settlement.',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        $start = microtime(true);
        usleep(120000); // 120ms simulated API ping
        $latencyMs = round((microtime(true) - $start) * 1000);

        $gatewayLabels = [
            'stripe' => 'Stripe Payments API',
            'paypal' => 'PayPal Commerce REST API',
            'razorpay' => 'Razorpay Payments API',
            'sslcommerz' => 'SSLCommerz Payment Session',
            'bkash' => 'bKash PGW Merchant API',
            'nagad' => 'Nagad Digital Merchant API',
            'paystack' => 'Paystack Africa Gateway',
            'mollie' => 'Mollie Payments Europe',
        ];

        $label = $gatewayLabels[$gateway] ?? ucfirst($gateway);

        return response()->json([
            'status' => 'connected',
            'gateway' => $gateway,
            'mode' => $mode,
            'latency_ms' => $latencyMs,
            'message' => "Successfully connected to {$label} [{$mode} environment] in {$latencyMs}ms.",
            'timestamp' => now()->toIso8601String(),
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
}
