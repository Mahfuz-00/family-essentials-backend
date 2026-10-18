<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\PushMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The local driver: records what would have been sent, and to which device.
 *
 * This is the default until credentials exist, so a fresh clone still runs end
 * to end and the notification log tells the truth about what was attempted.
 */
class LogPushSender implements PushTransport
{
    public function provider(): string
    {
        return 'log';
    }

    public function send(DeviceToken $token, PushMessage $message): ?string
    {
        $messageId = 'log-'.Str::uuid()->toString();

        Log::info('Push dispatched', [
            'device' => $token->id,
            'platform' => $token->platform,
            'style' => $message->style->value,
            'title' => $message->title,
            'body' => $message->body,
            'route' => $message->route,
            'message_id' => $messageId,
        ]);

        return $messageId;
    }

    /**
     * Convenience for anything that still wants a user-level send with no
     * registered device (nothing does; kept out of the interface on purpose).
     */
    public function sendToUser(User $recipient, PushMessage $message): ?string
    {
        Log::info('Push dispatched (no device)', [
            'to' => $recipient->email,
            'style' => $message->style->value,
            'title' => $message->title,
            'body' => $message->body,
        ]);

        return null;
    }
}
