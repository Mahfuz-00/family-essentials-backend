<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Notifications\PushMessage;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Apple Push Notification service, token-based (.p8) authentication.
 *
 * iOS constraints worth remembering: the sound must be a file bundled in the app
 * and no longer than 30 seconds, and it is chosen by the *sender*, not by the
 * device. Speech is not possible from a push — `voice` therefore arrives with a
 * sound and is spoken when the app opens.
 */
class ApnsPushTransport implements PushTransport
{
    public function __construct(
        private readonly string $keyPath,
        private readonly string $keyId,
        private readonly string $teamId,
        private readonly string $bundleId,
        private readonly bool $production = false,
    ) {}

    public function provider(): string
    {
        return 'apns';
    }

    public function send(DeviceToken $token, PushMessage $message): ?string
    {
        if ($this->bundleId === '') {
            throw new RuntimeException('APNS_BUNDLE_ID is not configured.');
        }

        $payload = [
            'aps' => array_filter([
                'alert' => [
                    'title' => $message->title,
                    'body' => $message->body,
                ],
                'sound' => $message->style->iosSound(),
                'interruption-level' => $message->style->iosInterruptionLevel(),
                'content-available' => 1,
            ], static fn ($value): bool => $value !== null),
        ] + $message->payload();

        $endpoint = $this->production
            ? 'https://api.push.apple.com'
            : 'https://api.sandbox.push.apple.com';

        try {
            $response = Http::withToken($this->jwt())
                ->withHeaders([
                    'apns-topic' => $this->bundleId,
                    'apns-push-type' => 'alert',
                    'apns-priority' => '10',
                ])
                // Apple only speaks HTTP/2.
                ->withOptions(['version' => '2.0'])
                ->post($endpoint.'/3/device/'.$token->token, $payload);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'APNs request failed (curl must support HTTP/2): '.$exception->getMessage()
            );
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'APNs rejected the message: '.$response->status().' '.$response->body()
            );
        }

        return $response->header('apns-id') ?: null;
    }

    private function jwt(): string
    {
        return Cache::remember('apns:jwt:'.md5($this->keyId), 3000, function (): string {
            if (! is_file($this->keyPath)) {
                throw new RuntimeException("APNs auth key not found at [{$this->keyPath}].");
            }

            return JWT::encode(
                ['iss' => $this->teamId, 'iat' => time()],
                (string) file_get_contents($this->keyPath),
                'ES256',
                $this->keyId,
            );
        });
    }
}
