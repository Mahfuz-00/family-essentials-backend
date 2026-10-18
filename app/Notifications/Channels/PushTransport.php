<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Notifications\PushMessage;

/**
 * A single delivery channel for one platform. Implementations must either
 * deliver or throw: a silent failure would be recorded as "sent".
 */
interface PushTransport
{
    /**
     * The identifier recorded in `notification_logs.provider`.
     */
    public function provider(): string;

    /**
     * @return string|null the provider's message id, when it gives one
     *
     * @throws \RuntimeException when delivery fails
     */
    public function send(DeviceToken $token, PushMessage $message): ?string;
}
