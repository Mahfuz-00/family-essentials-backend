<?php

namespace App\Reminders;

use App\Enums\AlertStyle;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\ReminderStatus;
use App\Models\NotificationLog;
use App\Models\Reminder;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\Channels\PushSender;
use App\Notifications\PushMessage;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Layer 2 delivery for a single due reminder: re-validates the request, honours
 * quiet hours, sends through the channel adapter and writes an audit row.
 */
class ReminderDispatcher
{
    public function __construct(
        private readonly PushSender $push,
        private readonly QuietHours $quietHours,
    ) {}

    public function dispatch(Reminder $reminder): void
    {
        $request = $reminder->request;

        if ($request === null || ! $request->status->isOpen()) {
            $reminder->forceFill([
                'status' => ReminderStatus::Cancelled,
                'cancelled_at' => Carbon::now(),
            ])->save();

            $this->log($reminder, null, NotificationStatus::Skipped, 'Request is no longer open.');

            return;
        }

        $recipient = $request->assignee ?? $request->requester;

        if ($recipient === null) {
            $this->fail($reminder);
            $this->log($reminder, null, NotificationStatus::Failed, 'No recipient for this request.');

            return;
        }

        $now = Carbon::now();

        if ($this->quietHours->isQuiet($request->tenant, $recipient, $now)) {
            $resumeAt = $this->quietHours->resumeAt($request->tenant, $recipient, $now);

            $reminder->forceFill(['fire_at' => $resumeAt])->save();

            $this->log($reminder, $recipient, NotificationStatus::Skipped, 'Deferred until quiet hours end.');

            return;
        }

        $title = 'Reminder: '.$request->title;
        $body = $this->message($request);

        try {
            $result = $this->push->send($recipient, new PushMessage(
                title: $title,
                body: $body,
                style: AlertStyle::fromUser($recipient->alert_style),
                data: ['request_id' => (string) $request->getKey()],
                route: 'request:'.$request->getKey(),
            ));
        } catch (Throwable $exception) {
            $this->fail($reminder);
            $this->log($reminder, $recipient, NotificationStatus::Failed, $exception->getMessage(), body: $body);

            return;
        }

        $reminder->forceFill([
            'status' => ReminderStatus::Sent,
            'dispatched_at' => Carbon::now(),
        ])->save();

        $this->log(
            $reminder,
            $recipient,
            NotificationStatus::Sent,
            provider: $result['provider'],
            messageId: $result['message_id'],
            body: $body,
        );
    }

    private function message(ServiceRequest $request): string
    {
        $parts = array_values(array_filter([$request->quantity, $request->store_name]));

        return $parts === [] ? $request->title : implode(' · ', $parts);
    }

    private function fail(Reminder $reminder): void
    {
        $reminder->forceFill(['status' => ReminderStatus::Failed])->save();
    }

    private function log(
        Reminder $reminder,
        ?User $recipient,
        NotificationStatus $status,
        ?string $note = null,
        ?string $provider = null,
        ?string $messageId = null,
        ?string $body = null,
    ): void {
        NotificationLog::create([
            'tenant_id' => $reminder->tenant_id,
            'request_id' => $reminder->request_id,
            'user_id' => $recipient?->getKey(),
            'reminder_id' => $reminder->getKey(),
            'channel' => NotificationChannel::Push,
            'event' => 'reminder.'.$reminder->type->value,
            'title' => $status === NotificationStatus::Sent ? 'Reminder sent' : 'Reminder not sent',
            'message' => $body ?? $note,
            'status' => $status,
            'provider' => $provider,
            'provider_message_id' => $messageId,
            'error' => $status === NotificationStatus::Failed ? $note : null,
            'sent_at' => $status === NotificationStatus::Sent ? Carbon::now() : null,
        ]);
    }
}
