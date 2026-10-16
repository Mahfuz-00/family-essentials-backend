<?php

namespace App\Notifications\Channels;

use App\Models\User;

/**
 * Layer 2 delivery. Phase 4 ships a log driver; swapping in real FCM/APNs is a
 * config change once credentials exist.
 */
interface PushSender
{
    /**
     * @return array{provider: string, message_id: string|null}
     */
    public function send(User $recipient, string $title, string $body): array;
}
