<?php

namespace App\Payments;

use App\Models\Invoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paddle Billing over the REST API — no SDK, no stored card data.
 *
 * A checkout is a Paddle transaction built from one configured catalog price:
 * Paddle charges that price, so the invoice amount is only informational. The
 * transaction id is what a later status check queries, and the invoice is
 * settled by the signed webhook, never by the redirect back to the app.
 *
 * The console may hold either a price id (`pri_…`) or a product id (`pro_…`).
 * A product id is resolved to the product's first active price before the
 * transaction is created.
 *
 * The redirect URL a buyer lands on after paying is configured in the Paddle
 * dashboard (Checkout settings), not per transaction: the success and cancel
 * URLs passed to createCheckout() are therefore ignored, and the invoice is
 * recognized through `custom_data.invoice_id` on the webhook instead.
 *
 * Paddle also refuses to create a transaction for an account with no default
 * payment link at all. When it says so, the configured link is PATCHed onto
 * `settings/checkout-url` and the transaction is retried once. The console pays
 * through a Paddle.js overlay keyed by `client_token` + transaction id, so the
 * link only has to satisfy Paddle — nobody is sent to that page.
 *
 * Mode follows the API key prefix — `pdl_live_` is live, anything else
 * (`pdl_sdbx_`, and the test fixture keys) is sandbox — and selects the host.
 */
final class PaddleGateway implements PaymentGateway
{
    private const LIVE_BASE = 'https://api.paddle.com';

    private const SANDBOX_BASE = 'https://sandbox-api.paddle.com';

    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    private ?string $resolvedPriceId = null;

    public function __construct(
        private readonly GatewayCredentials $credentials,
        private readonly ?string $mode = null,
    ) {}

    /**
     * Paddle keys are `pdl_sdbx_apikey_…` / `pdl_live_apikey_…`. Some dashboards
     * copy only the `apikey_…` suffix, so put the mode prefix back and tolerate a
     * pasted "Bearer " prefix or surrounding quotes.
     */
    public static function normaliseApiKey(?string $key, string $mode): ?string
    {
        if (! is_string($key)) {
            return null;
        }

        $value = trim($key);
        $value = trim($value, "\"'");
        $value = preg_replace('/^Bearer\s+/i', '', $value) ?? $value;
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'apikey_')) {
            $value = ($mode === 'live' ? 'pdl_live_' : 'pdl_sdbx_').$value;
        }

        return $value;
    }

    public function key(): string
    {
        return 'paddle';
    }

    public function isConfigured(): bool
    {
        return $this->credentials->isConfigured($this->key(), $this->mode());
    }

    public function mode(): string
    {
        return $this->mode ?? $this->credentials->clientMode($this->key());
    }

    public function createCheckout(Invoice $invoice, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $apiKey = $this->requireApiKey();
        $priceId = $this->resolvePriceId($invoice);

        $response = $this->postTransaction($apiKey, $priceId, $invoice);
        $payload = $response->json();

        // Paddle refuses to build a transaction for an account with no default
        // checkout link. Set the configured link and retry once: the second
        // failure is reported as it is, never retried again.
        if ($response->failed() && $this->isDefaultPaymentLinkMissing($payload)) {
            $this->ensureDefaultPaymentLink();

            $response = $this->postTransaction($apiKey, $priceId, $invoice);
            $payload = $response->json();
        }

        if ($response->failed() || ! is_array($payload)) {
            throw new RuntimeException(
                'Paddle transaction failed: '.$this->errorDetail($payload, $response->status()),
            );
        }

        $transaction = $payload['data'] ?? null;
        $id = is_array($transaction) ? ($transaction['id'] ?? null) : null;
        $url = is_array($transaction) ? ($transaction['checkout']['url'] ?? null) : null;

        if (! is_string($id) || ! is_string($url) || $url === '') {
            throw new RuntimeException('Paddle returned a transaction without a checkout URL.');
        }

        return new CheckoutSession($id, $url, $this->key(), $this->mode());
    }

    /**
     * The client-side token (`test_…` sandbox, `live_…` live) the admin drawer
     * hands to Paddle.js to open the checkout as an overlay. Public by design,
     * so it is returned as stored rather than masked.
     */
    public function clientToken(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['client_token'] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Paddle will not create a transaction until the account has a default
     * payment link. Set it from the configured URL so a first checkout succeeds
     * on its own.
     *
     * The link only satisfies Paddle: the console opens the checkout as a
     * Paddle.js overlay, so nobody is ever sent to this page. Paddle still
     * insists on an https URL — http and localhost are rejected, and live only
     * accepts a domain approved in the Paddle dashboard.
     */
    public function ensureDefaultPaymentLink(): void
    {
        $link = $this->defaultPaymentLink();

        if ($link === null) {
            throw new RuntimeException(
                'Paddle requires a default payment link before it will create a transaction. '
                .'Add an https URL in the Paddle card\'s "Default Payment Link" field and save it: '
                .'the sandbox accepts any https URL, while live needs a checkout domain approved in Paddle.',
            );
        }

        if (! str_starts_with($link, 'https://')) {
            throw new RuntimeException(
                'Paddle requires the default payment link to start with https:// — http and localhost URLs are rejected. '
                .'The sandbox checkout opens as a Paddle.js overlay, so this page is never visited; any https URL works in sandbox.',
            );
        }

        $apiKey = $this->requireApiKey();

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->patch($this->baseUrl().'/settings/checkout-url', ['default_url' => $link]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach Paddle: '.$exception->getMessage(), 0, $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'Paddle could not set the default payment link: '
                .$this->errorDetail($response->json(), $response->status())
                .'. Add the checkout_settings.write permission to the API key, or set it in Paddle > Checkout > Checkout configuration.',
            );
        }
    }

    public function verifyTestPayment(string $reference): array
    {
        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            return ['status' => 'unknown', 'message' => 'Paddle is not configured: the API key is missing.'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20)
                ->get($this->baseUrl().'/transactions/'.rawurlencode($reference));
        } catch (ConnectionException $exception) {
            return ['status' => 'unknown', 'message' => 'Could not reach Paddle: '.$exception->getMessage()];
        }

        if ($response->failed()) {
            return [
                'status' => 'unknown',
                'message' => 'Paddle rejected the status lookup: '
                    .$this->errorDetail($response->json(), $response->status()),
            ];
        }

        $status = strtolower((string) $response->json('data.status'));

        if (in_array($status, ['completed', 'paid'], true)) {
            return ['status' => 'paid', 'message' => 'Paddle confirms the transaction was paid.'];
        }

        if ($status === 'canceled') {
            return ['status' => 'failed', 'message' => 'The Paddle transaction was canceled.'];
        }

        if (in_array($status, ['draft', 'ready', 'billed', 'past_due'], true)) {
            return ['status' => 'pending', 'message' => 'Paddle is still waiting for the payment.'];
        }

        return ['status' => 'unknown', 'message' => 'Paddle answered with an unrecognised status.'];
    }

    public function verifyWebhook(Request $request): PaymentEvent
    {
        $secret = $this->requireWebhookSecret();
        $payload = $request->getContent();

        $this->assertSignature($payload, (string) $request->header('Paddle-Signature', ''), $secret);

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new RuntimeException('Paddle webhook payload is not valid JSON.');
        }

        $type = (string) ($event['event_type'] ?? 'unknown');
        $data = $event['data'] ?? [];
        $data = is_array($data) ? $data : [];

        $customData = $data['custom_data'] ?? [];
        $customData = is_array($customData) ? $customData : [];
        $invoiceId = $customData['invoice_id'] ?? null;

        return new PaymentEvent(
            type: $type,
            paid: $type === 'transaction.completed' && ($data['status'] ?? null) === 'completed',
            invoiceId: is_numeric($invoiceId) ? (int) $invoiceId : null,
            providerReference: (string) ($data['id'] ?? ''),
            payload: $event,
        );
    }

    public function confirm(PaymentEvent $event): PaymentEvent
    {
        return $event;
    }

    /**
     * Paddle sends `Paddle-Signature: ts=<unix>;h1=<hex>`; the digest covers
     * `"{ts}:{raw body}"`. A signature outside the tolerance window is stale and
     * rejected even when the digest matches.
     */
    private function assertSignature(string $payload, string $header, string $secret): void
    {
        $parts = [];

        foreach (explode(';', $header) as $chunk) {
            [$key, $value] = array_pad(explode('=', trim($chunk), 2), 2, null);

            if ($key !== null && $value !== null && $key !== '') {
                $parts[$key][] = $value;
            }
        }

        $timestamp = (int) ($parts['ts'][0] ?? 0);
        $signatures = $parts['h1'] ?? [];

        if ($timestamp === 0 || $signatures === []) {
            throw new RuntimeException('Missing Paddle signature header.');
        }

        if (abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            throw new RuntimeException('Paddle signature timestamp is outside the tolerance window.');
        }

        $expected = hash_hmac('sha256', $timestamp.':'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new RuntimeException('Invalid Paddle signature.');
    }

    private function baseUrl(): string
    {
        return $this->mode() === 'live' ? self::LIVE_BASE : self::SANDBOX_BASE;
    }

    private function apiKey(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['api_key'] ?? null;

        return self::normaliseApiKey(is_string($value) ? $value : null, $this->mode());
    }

    private function priceId(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['price_id'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function testPriceId(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['test_price_id'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function webhookSecret(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['webhook_secret'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function requireApiKey(): string
    {
        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            throw new RuntimeException('Paddle is not configured: the API key is missing.');
        }

        if (! str_starts_with($apiKey, 'pdl_sdbx_apikey_') && ! str_starts_with($apiKey, 'pdl_live_apikey_')) {
            throw new RuntimeException(
                'Paddle API key is malformed: it must start with pdl_sdbx_apikey_ (sandbox) or pdl_live_apikey_ (live).',
            );
        }

        // A full Paddle key is 69 characters; a shorter value was pasted truncated.
        if (strlen($apiKey) < 60) {
            throw new RuntimeException(
                'Paddle API key looks incomplete ('.strlen($apiKey).' characters). Paste the full key from Paddle → Developer Tools → Authentication.',
            );
        }

        return $apiKey;
    }

    /**
     * The transaction POST, kept separate so the default-payment-link retry is
     * the same request as the original rather than a second, drifting copy.
     */
    private function postTransaction(string $apiKey, string $priceId, Invoice $invoice): Response
    {
        try {
            return Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post($this->baseUrl().'/transactions', [
                    'items' => [[
                        'price_id' => $priceId,
                        'quantity' => 1,
                    ]],
                    'collection_mode' => 'automatic',
                    'custom_data' => [
                        'invoice_id' => (string) $invoice->getKey(),
                        'tenant_id' => (string) $invoice->tenant_id,
                        'tier' => (string) $invoice->tier,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach Paddle: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Paddle answers a transaction on an account with no default checkout link
     * with `transaction_default_checkout_url_not_set`. Older responses only say
     * so in the detail, so both are recognised.
     */
    private function isDefaultPaymentLinkMissing(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $error = $payload['error'] ?? null;

        if (! is_array($error)) {
            return false;
        }

        if ((string) ($error['code'] ?? '') === 'transaction_default_checkout_url_not_set') {
            return true;
        }

        return str_contains(strtolower((string) ($error['detail'] ?? '')), 'default payment link');
    }

    private function defaultPaymentLink(): ?string
    {
        $value = $this->credentials->resolve($this->key(), $this->mode())['default_payment_link'] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * The price a transaction is built from. A `pro_…` product id is resolved to
     * the product's first active price, so the console may hold either id.
     */
    private function resolvePriceId(Invoice $invoice): string
    {
        if ($this->resolvedPriceId !== null) {
            return $this->resolvedPriceId;
        }

        // The console's Sandbox payment uses a dedicated test price when one is
        // configured; a normal checkout (INV-… invoice) never does.
        $isTestInvoice = str_starts_with((string) $invoice->invoice_number, 'TEST-');
        $configured = $isTestInvoice
            ? ($this->testPriceId() ?? $this->priceId())
            : $this->priceId();

        if ($configured === null) {
            throw new RuntimeException('Paddle is not configured: the price id is missing.');
        }

        if (str_starts_with($configured, 'pri_')) {
            return $this->resolvedPriceId = $configured;
        }

        $apiKey = $this->requireApiKey();
        $priceId = $this->firstPriceForProduct($apiKey, $configured);

        // A new sandbox product often has no price yet. Create a one-off test
        // price from the invoice so the console can finish the checkout; live
        // catalogs are never mutated here.
        if ($priceId === null && $this->mode() === 'sandbox') {
            $priceId = $this->createSandboxPrice(
                $apiKey,
                $configured,
                (int) $invoice->amount_cents,
                (string) $invoice->currency,
            );
        }

        if ($priceId === null) {
            throw new RuntimeException(
                "Paddle product [{$configured}] has no usable price. Add a price in Paddle, or paste the price id (pri_…).",
            );
        }

        return $this->resolvedPriceId = $priceId;
    }

    private function createSandboxPrice(string $apiKey, string $productId, int $amountCents, string $currency): ?string
    {
        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post($this->baseUrl().'/prices', [
                    'product_id' => $productId,
                    'description' => 'FairShare sandbox test price',
                    'unit_price' => [
                        'amount' => (string) max(1, $amountCents),
                        'currency_code' => strtoupper($currency !== '' ? $currency : 'USD'),
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach Paddle: '.$exception->getMessage(), 0, $exception);
        }

        $payload = $response->json();

        if ($response->failed() || ! is_array($payload)) {
            throw new RuntimeException(
                'Paddle price creation failed: '.$this->errorDetail($payload, $response->status()),
            );
        }

        $priceId = $payload['data']['id'] ?? null;

        return is_string($priceId) && $priceId !== '' ? $priceId : null;
    }

    private function firstPriceForProduct(string $apiKey, string $productId): ?string
    {
        $queries = [
            ['product_id' => $productId, 'status' => 'active', 'per_page' => 1],
            ['product_id' => $productId, 'per_page' => 1],
        ];

        foreach ($queries as $query) {
            try {
                $response = Http::withToken($apiKey)
                    ->acceptJson()
                    ->timeout(20)
                    ->get($this->baseUrl().'/prices', $query);
            } catch (ConnectionException $exception) {
                throw new RuntimeException('Could not reach Paddle: '.$exception->getMessage(), 0, $exception);
            }

            $payload = $response->json();

            if ($response->failed() || ! is_array($payload)) {
                throw new RuntimeException(
                    'Paddle price lookup failed: '.$this->errorDetail($payload, $response->status()),
                );
            }

            $priceId = $payload['data'][0]['id'] ?? null;

            if (is_string($priceId) && $priceId !== '') {
                return $priceId;
            }
        }

        return null;
    }

    /**
     * Paddle puts a short `error.detail` and a nested `error.errors` map in a
     * failure. Include the first field message so the console is actionable.
     */
    private function errorDetail(mixed $payload, int $status = 0): string
    {
        if (! is_array($payload)) {
            return $status > 0 ? 'HTTP '.$status : 'unknown error';
        }

        $detail = (string) ($payload['error']['detail'] ?? ($status > 0 ? 'HTTP '.$status : 'unknown error'));
        $errors = $payload['error']['errors'] ?? null;

        if (is_array($errors)) {
            $first = $this->firstErrorMessage($errors);

            if ($first !== null) {
                return $detail.' ('.$first.')';
            }
        }

        return $detail;
    }

    /** @param array<mixed> $errors */
    private function firstErrorMessage(array $errors): ?string
    {
        foreach ($errors as $error) {
            if (is_array($error)) {
                $message = $error['message'] ?? null;

                if (is_string($message) && trim($message) !== '') {
                    return trim($message);
                }

                $nested = $this->firstErrorMessage($error);

                if ($nested !== null) {
                    return $nested;
                }

                continue;
            }

            if (is_string($error) && trim($error) !== '') {
                return trim($error);
            }
        }

        return null;
    }

    private function requireWebhookSecret(): string
    {
        $secret = $this->webhookSecret();

        if ($secret === null) {
            throw new RuntimeException('Paddle webhook secret is missing.');
        }

        return $secret;
    }
}
