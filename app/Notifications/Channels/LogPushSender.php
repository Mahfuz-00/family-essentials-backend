<?php

namespace App\Notifications\Channels;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogPushSender implements PushSender
{
    /**
     * @return array{provider: string, message_id: string|null}
     */
    public function send(User $recipient, string $title, string $body): array
    {
        $messageId = 'log-'.Str::uuid()->toString();

        Log::info('Push dispatched', [
            'to' => $recipient->email,
            'title' => $title,
            'body' => $body,
            'message_id' => $messageId,
        ]);

        return ['provider' => 'log', 'message_id' => $messageId];
    }
}
