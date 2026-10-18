<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\PushMessage;

/**
 * Delivery to a member's registered devices.
 *
 * The channel is chosen by token platform, so Android goes through FCM and iOS
 * through APNs without either call site knowing.
 */
interface PushSender
{
    /**
     * @return array{provider: string, message_id: string|null}
     *
     * @throws \RuntimeException when nothing could be delivered
     */
    public function send(User $recipient, PushMessage $message): array;
}
