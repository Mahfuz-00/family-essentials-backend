<?php

namespace App\Payments;

use App\Models\PlatformSetting;

/**
 * The payment gateway credential resolver.
 *
 * The admin console is the source of truth: credentials saved in
 * `platform_settings.payment_gateway` win, and the environment is only a
 * fallback so an install that predates the settings screen keeps working.
 *
 * Both fallbacks are mode-matched. A live lookup never sees sandbox environment
 * keys and a sandbox lookup never sees live ones, so a half-configured gateway
 * fails loudly instead of charging in the wrong environment.
 */
final class GatewayCredentials
{
    /** What a read returns in place of a stored secret. */
    public const MASK = '••••••••';

    private const MODES = ['sandbox', 'live'];

    /**
     * Every credential field the console edits, per gateway.
     *
     * @var array<string, list<string>>
     */
    private const FIELDS = [
        'stripe' => ['publishable_key', 'secret_key', 'webhook_secret'],
        'paypal' => ['client_id', 'client_secret', 'webhook_id'],
        'razorpay' => ['key_id', 'key_secret', 'webhook_secret'],
        'sslcommerz' => ['store_id', 'store_passwd'],
        'bkash' => ['app_key', 'app_secret', 'username', 'password'],
        'nagad' => ['merchant_id', 'public_key', 'private_key'],
        'paystack' => ['public_key', 'secret_key'],
        'mollie' => ['api_key', 'webhook_url'],
        'paddle' => ['api_key', 'webhook_secret', 'price_id', 'test_price_id', 'client_token', 'default_payment_link'],
        'bank_transfer' => ['bank_name', 'account_name', 'account_number', 'routing_code', 'swift_bic', 'instructions'],
        'cash' => ['instructions', 'auto_mark_paid'],
        'mock' => ['simulate_failure', 'latency_ms'],
    ];

    /**
     * Fields that are never returned or audited in the clear.
     *
     * @var array<string, list<string>>
     */
    private const SECRET_FIELDS = [
        'stripe' => ['secret_key', 'webhook_secret'],
        'paypal' => ['client_secret'],
        'razorpay' => ['key_secret', 'webhook_secret'],
        'sslcommerz' => ['store_passwd'],
        'bkash' => ['app_secret', 'password'],
        'nagad' => ['private_key'],
        'paystack' => ['secret_key'],
        'mollie' => ['api_key'],
        // Paddle's client-side token is public by design — Paddle.js receives it
        // in the browser — but the console still masks it like any credential.
        'paddle' => ['api_key', 'webhook_secret', 'client_token'],
        'bank_transfer' => ['account_number'],
    ];

    /**
     * Fields a gateway cannot be used without. Gateways that are not listed here
     * (cash, bank_transfer, mock, nagad) are never reported as configured.
     *
     * @var array<string, list<string>>
     */
    private const REQUIRED = [
        'stripe' => ['secret_key', 'webhook_secret'],
        'paypal' => ['client_id', 'client_secret', 'webhook_id'],
        'razorpay' => ['key_id', 'key_secret', 'webhook_secret'],
        'sslcommerz' => ['store_id', 'store_passwd'],
        'bkash' => ['app_key', 'app_secret', 'username', 'password'],
        'paystack' => ['secret_key'],
        'mollie' => ['api_key'],
        // The client-side token opens the checkout overlay, so a Paddle card
        // without it cannot pay.
        'paddle' => ['api_key', 'webhook_secret', 'price_id', 'client_token'],
    ];

    /**
     * Fields a connectivity probe needs. Webhook secrets are deliberately absent:
     * a ping checks the provider API, not the callback signature.
     *
     * @var array<string, list<string>>
     */
    private const PROBE_REQUIRED = [
        'stripe' => ['secret_key'],
        'paypal' => ['client_id', 'client_secret'],
        'razorpay' => ['key_id', 'key_secret'],
        'sslcommerz' => ['store_id', 'store_passwd'],
        'bkash' => ['app_key', 'app_secret', 'username', 'password'],
        'paystack' => ['secret_key'],
        'mollie' => ['api_key'],
        'paddle' => ['api_key', 'price_id'],
    ];

    /**
     * Where each field falls back to when nothing is stored, and under which mode.
     *
     * @var array<string, array<string, string>>
     */
    private const ENV_FIELDS = [
        'stripe' => [
            'publishable_key' => 'services.stripe.key',
            'secret_key' => 'services.stripe.secret',
            'webhook_secret' => 'services.stripe.webhook_secret',
        ],
        'paypal' => [
            'client_id' => 'services.paypal.client_id',
            'client_secret' => 'services.paypal.client_secret',
            'webhook_id' => 'services.paypal.webhook_id',
        ],
        'razorpay' => [
            'key_id' => 'services.razorpay.key_id',
            'key_secret' => 'services.razorpay.key_secret',
            'webhook_secret' => 'services.razorpay.webhook_secret',
        ],
        'sslcommerz' => [
            'store_id' => 'services.sslcommerz.store_id',
            'store_passwd' => 'services.sslcommerz.store_password',
        ],
        'bkash' => [
            'app_key' => 'services.bkash.app_key',
            'app_secret' => 'services.bkash.app_secret',
            'username' => 'services.bkash.username',
            'password' => 'services.bkash.password',
        ],
        'paystack' => [
            'secret_key' => 'services.paystack.secret_key',
        ],
        'mollie' => [
            'api_key' => 'services.mollie.api_key',
            'webhook_url' => 'services.mollie.webhook_url',
        ],
        'paddle' => [
            'api_key' => 'services.paddle.api_key',
            'webhook_secret' => 'services.paddle.webhook_secret',
            'price_id' => 'services.paddle.price_id',
            'test_price_id' => 'services.paddle.test_price_id',
            'client_token' => 'services.paddle.client_token',
            'default_payment_link' => 'services.paddle.default_payment_link',
        ],
    ];

    /**
     * The credentials a gateway should be used with in the given mode.
     *
     * Precedence, highest first: the stored `credentials[$mode]` set, the legacy
     * flat fields when their mode matches, then the environment when its mode
     * matches. Empty and masked values count as absent at every step.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $gateway, string $mode): array
    {
        $mode = $this->normaliseMode($mode);
        $config = $this->gatewayConfig($gateway);

        $stored = $this->credentialSet($config, $mode);
        $legacy = $this->legacyMode($config) === $mode ? $this->legacyFields($gateway, $config) : [];
        $env = $this->envMode($gateway) === $mode ? $this->envFields($gateway) : [];

        $resolved = [];

        foreach ($this->fields($gateway) as $field) {
            foreach ([$stored[$field] ?? null, $legacy[$field] ?? null, $env[$field] ?? null] as $value) {
                if ($this->isUsable($value)) {
                    $resolved[$field] = $value;
                    break;
                }
            }
        }

        return $resolved;
    }

    /**
     * The mode the console means by this gateway: an explicit `sandbox` flag if
     * one is stored, otherwise the mode the environment credentials belong to.
     */
    public function clientMode(string $gateway): string
    {
        $config = $this->gatewayConfig($gateway);

        if (array_key_exists('sandbox', $config) && $config['sandbox'] !== null) {
            return $this->legacyMode($config);
        }

        return $this->envMode($gateway);
    }

    /** Whether every field the gateway needs is present in the given mode. */
    public function isConfigured(string $gateway, string $mode): bool
    {
        $required = self::REQUIRED[$gateway] ?? null;

        if ($required === null) {
            return false;
        }

        $resolved = $this->resolve($gateway, $mode);

        foreach ($required as $field) {
            if (! $this->isUsable($resolved[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The fields a connectivity probe is still missing in this mode. This is a
     * subset of isConfigured(): a ping never needs the webhook secret.
     *
     * @return list<string>
     */
    public function missingForProbe(string $gateway, string $mode): array
    {
        $required = self::PROBE_REQUIRED[$gateway] ?? null;

        if ($required === null) {
            return ['credentials'];
        }

        $resolved = $this->resolve($gateway, $mode);

        return array_values(array_filter(
            $required,
            fn (string $field): bool => ! $this->isUsable($resolved[$field] ?? null),
        ));
    }

    /**
     * Replace every secret with the mask sentinel. A value that is empty, or that
     * already carries bullets from an older save, is masked too so a stale mask
     * is never echoed back as if it were a credential.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    public function mask(array $credentials): array
    {
        $masked = [];

        foreach ($credentials as $field => $value) {
            if (is_array($value)) {
                $masked[$field] = $this->mask($value);

                continue;
            }

            $secret = in_array((string) $field, $this->secretFieldNames(), true);
            $alreadyMasked = is_string($value) && str_contains($value, '•');

            $masked[$field] = ($secret || $alreadyMasked) && $value !== null && $value !== ''
                ? self::MASK
                : $value;
        }

        return $masked;
    }

    /**
     * Overlay a submitted credential array on the stored one. An empty, null or
     * masked submission keeps the stored value, so a screen that only round-trips
     * the mask can never erase a secret.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    public function mergeSubmitted(array $existing, array $submitted): array
    {
        $merged = $existing;

        foreach ($submitted as $field => $value) {
            if (is_array($value)) {
                $current = $existing[$field] ?? [];
                $merged[$field] = $this->mergeSubmitted(is_array($current) ? $current : [], $value);

                continue;
            }

            if (! $this->isUsable($value)) {
                continue;
            }

            $merged[$field] = $value;
        }

        return $merged;
    }

    /** @return list<string> */
    public function fields(string $gateway): array
    {
        return self::FIELDS[$gateway] ?? [];
    }

    /** @return list<string> */
    public function secretFields(string $gateway): array
    {
        return self::SECRET_FIELDS[$gateway] ?? [];
    }

    /** @return list<string> */
    public function gatewayKeys(): array
    {
        return array_keys(self::FIELDS);
    }

    /**
     * Both credential sets as they are stored, with legacy flat fields folded into
     * the set the gateway's `sandbox` flag points at. Reading only: nothing here
     * is persisted back.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, array<string, mixed>>
     */
    public function storedSets(string $gateway, array $config): array
    {
        $sets = [];

        foreach (self::MODES as $mode) {
            $sets[$mode] = $this->credentialSet($config, $mode);
        }

        if (! isset($config['credentials'])) {
            $legacyMode = $this->legacyMode($config);

            foreach ($this->legacyFields($gateway, $config) as $field => $value) {
                if (! array_key_exists($field, $sets[$legacyMode]) && $this->isUsable($value)) {
                    $sets[$legacyMode][$field] = $value;
                }
            }
        }

        return $sets;
    }

    /**
     * The stored `credentials[$mode]` set, without legacy fields.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function credentialSet(array $config, string $mode): array
    {
        $credentials = $config['credentials'] ?? null;

        if (! is_array($credentials)) {
            return [];
        }

        $set = $credentials[$mode] ?? null;

        if (! is_array($set)) {
            return [];
        }

        return array_filter(
            $set,
            fn ($value): bool => ! is_array($value) && $this->isUsable($value),
        );
    }

    /**
     * The legacy flat credential fields stored at the gateway level.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function legacyFields(string $gateway, array $config): array
    {
        $fields = [];

        foreach ($this->fields($gateway) as $field) {
            if (array_key_exists($field, $config) && ! is_array($config[$field])) {
                $fields[$field] = $config[$field];
            }
        }

        return $fields;
    }

    /**
     * The environment credentials for a gateway, keyed by credential field.
     *
     * @return array<string, mixed>
     */
    private function envFields(string $gateway): array
    {
        $fields = [];

        foreach (self::ENV_FIELDS[$gateway] ?? [] as $field => $path) {
            $fields[$field] = config($path);
        }

        return $fields;
    }

    /**
     * The mode the environment credentials belong to.
     *
     * Stripe, Razorpay, Paystack, Mollie and Paddle publish it in the key prefix;
     * the others (and any gateway with no environment block at all) declare it.
     */
    private function envMode(string $gateway): string
    {
        return match ($gateway) {
            'stripe' => str_starts_with((string) config('services.stripe.secret'), 'sk_live_') ? 'live' : 'sandbox',
            'razorpay' => str_starts_with((string) config('services.razorpay.key_id'), 'rzp_live_') ? 'live' : 'sandbox',
            'paystack' => str_starts_with((string) config('services.paystack.secret_key'), 'sk_live_') ? 'live' : 'sandbox',
            'mollie' => str_starts_with((string) config('services.mollie.api_key'), 'live_') ? 'live' : 'sandbox',
            'paddle' => str_starts_with((string) config('services.paddle.api_key'), 'pdl_live_') ? 'live' : 'sandbox',
            'paypal' => $this->declaredMode('services.paypal.mode'),
            'sslcommerz' => $this->declaredMode('services.sslcommerz.mode'),
            'bkash' => $this->declaredMode('services.bkash.mode'),
            default => 'sandbox',
        };
    }

    private function declaredMode(string $path): string
    {
        return config($path, 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    /**
     * The mode the legacy flat fields belong to; sandbox unless the gateway is
     * explicitly not in sandbox.
     *
     * @param  array<string, mixed>  $config
     */
    private function legacyMode(array $config): string
    {
        return filter_var($config['sandbox'] ?? true, FILTER_VALIDATE_BOOLEAN) === true
            ? 'sandbox'
            : 'live';
    }

    private function normaliseMode(string $mode): string
    {
        return $mode === 'live' ? 'live' : 'sandbox';
    }

    /** A value we are willing to hand to a driver: not empty, not a mask. */
    private function isUsable(mixed $value): bool
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        return trim($value) !== '' && ! str_contains($value, '•');
    }

    /** @return list<string> */
    private function secretFieldNames(): array
    {
        static $names = null;

        return $names ??= array_values(array_unique(array_merge(...array_values(self::SECRET_FIELDS))));
    }

    /**
     * The stored config for one gateway, with no defaults mixed in: a default is
     * presentation, not a credential.
     *
     * @return array<string, mixed>
     */
    private function gatewayConfig(string $gateway): array
    {
        $settings = PlatformSetting::get('payment_gateway', []);
        $gateways = is_array($settings) && is_array($settings['gateways'] ?? null)
            ? $settings['gateways']
            : [];

        $config = $gateways[$gateway] ?? [];

        return is_array($config) ? $config : [];
    }
}
