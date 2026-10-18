<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\PushMessage;
use App\Tenancy\Tenancy;
use RuntimeException;
use Throwable;

/**
 * Resolves a member's registered devices and hands each to the transport for its
 * platform.
 *
 * A send that reaches no device throws: the caller records the failure, so the
 * log never claims a notification was delivered when there was nowhere to send
 * it.
 */
class DevicePushSender implements PushSender
{
    /**
     * @param  array<string, PushTransport>  $transports  keyed by token platform
     * @param  bool  $requiresDevice  false only while every transport is the log
     *                                driver, where there is nothing to deliver to
     *                                and demanding a device would mark every send
     *                                failed in development
     */
    public function __construct(
        private readonly array $transports,
        private readonly bool $requiresDevice = true,
    ) {}

    public function send(User $recipient, PushMessage $message): array
    {
        // Delivery belongs to the person, not to the household: a member who
        // registered their phone while another household was active must still
        // receive this one. So the lookup steps around both the tenant global
        // scope and RLS, and is narrowed by user id instead.
        $tokens = Tenancy::bypass(fn () => DeviceToken::query()
            ->withoutGlobalScope('tenant')
            ->where('user_id', $recipient->getKey())
            ->get());

        if ($tokens->isEmpty()) {
            if ($this->requiresDevice) {
                throw new RuntimeException('No registered device for this member.');
            }

            // Log drivers: the notification log row is the record; there is no
            // device to hand this to.
            return ['provider' => 'log', 'message_id' => null];
        }

        $delivered = [];
        $errors = [];

        foreach ($tokens as $token) {
            $transport = $this->transports[$token->platform] ?? null;

            if ($transport === null) {
                $errors[] = "No push transport configured for [{$token->platform}].";

                continue;
            }

            try {
                $delivered[] = [
                    'provider' => $transport->provider(),
                    'message_id' => $transport->send($token, $message),
                ];
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($delivered === []) {
            throw new RuntimeException(implode(' ', array_slice($errors, 0, 3)));
        }

        return [
            'provider' => $delivered[0]['provider'],
            'message_id' => $delivered[0]['message_id'],
        ];
    }
}
