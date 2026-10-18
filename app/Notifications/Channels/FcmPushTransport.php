<?php

namespace App\Notifications\Channels;

use App\Enums\AlertStyle;
use App\Models\DeviceToken;
use App\Notifications\PushMessage;
use App\Support\GoogleServiceAccountToken;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging, HTTP v1.
 *
 * The Android notification channel is chosen from the member's alert style: the
 * channel itself owns the sound and vibration, because both are fixed when the
 * app creates it.
 */
class FcmPushTransport implements PushTransport
{
    public function __construct(
        private readonly string $projectId,
        private readonly GoogleServiceAccountToken $token,
    ) {}

    public function provider(): string
    {
        return 'fcm';
    }

    public function send(DeviceToken $token, PushMessage $message): ?string
    {
        if ($this->projectId === '') {
            throw new RuntimeException('FCM_PROJECT_ID is not configured.');
        }

        $response = Http::withToken($this->token->accessToken())
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                'message' => [
                    'token' => $token->token,
                    'notification' => [
                        'title' => $message->title,
                        'body' => $message->body,
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => $message->style->androidChannel(),
                            // The channel's sound wins; do not override it.
                            'default_sound' => false,
                            'default_vibrate_timings' => $message->style !== AlertStyle::Silent,
                        ],
                    ],
                    'data' => $message->payload(),
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'FCM rejected the message: '.$response->status().' '.$response->body()
            );
        }

        return $response->json('name');
    }
}
