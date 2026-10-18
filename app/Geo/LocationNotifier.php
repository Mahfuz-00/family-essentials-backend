<?php

namespace App\Geo;

use App\Enums\AlertStyle;
use App\Enums\GeofenceEventType;
use App\Enums\LocationKind;
use App\Enums\LocationNotificationReason;
use App\Enums\LocationNotificationStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\RequestStatus;
use App\Models\GeofenceEvent;
use App\Models\LocationNotification;
use App\Models\NotificationLog;
use App\Models\Place;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\Channels\PushSender;
use App\Notifications\PushMessage;
use App\Reminders\QuietHours;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Tells a member when a cue they chose fires: a saved place they asked to be
 * reminded by, on entering or on leaving it.
 *
 * The trigger lives on the task (`location_trigger_place_id` +
 * `location_trigger_direction`) — not on the place, and not on the task's shop.
 * "Buy chips" and "remind me when I leave work" are different facts.
 *
 * Four rules govern everything here:
 *
 *  1. One decision per recorded entry, enforced by a unique idempotency key, so a
 *     repeated position report cannot notify twice.
 *  2. A refusal is recorded with its reason, never silently dropped.
 *  3. Only the reader's own open work counts — the store shopping list stays
 *     everyone's and is unchanged.
 *  4. One nudge, not two: a task reminded by time in the last few minutes is left
 *     out of the location notification.
 */
class LocationNotifier
{
    public function __construct(
        private readonly PushSender $push,
        private readonly QuietHours $quietHours,
    ) {}

    public function onTransition(User $user, Place $place, GeofenceEvent $event): LocationNotification
    {
        $kind = $place->kind();
        $direction = $event->event;

        if (! (bool) config('location_notifications.enabled', true)) {
            return $this->refuse($user, $place, $event, $kind, LocationNotificationReason::Disabled);
        }

        if (! (bool) $user->location_notifications_opt_in) {
            return $this->refuse($user, $place, $event, $kind, LocationNotificationReason::NotOptedIn);
        }

        $entryKey = $this->entryKey($user, $place, $event);

        $existing = LocationNotification::query()->where('idempotency_key', $entryKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        $tenant = $place->tenant;

        if ($tenant !== null && $this->quietHours->isQuiet($tenant, $user, Carbon::now())) {
            return $this->refuse($user, $place, $event, $kind, LocationNotificationReason::QuietHours);
        }

        if ($this->withinCooldown($user, $place)) {
            return $this->refuse($user, $place, $event, $kind, LocationNotificationReason::Cooldown);
        }

        $tasks = $this->triggeredTasks($user, $place, $direction);

        if ($tasks->isEmpty()) {
            return $this->refuse($user, $place, $event, $kind, LocationNotificationReason::NoTasks);
        }

        // One nudge, not two: drop anything a time reminder already covered.
        $recentlyNudged = $this->recentlyTimeNudged($user, $tasks);
        $tasks = $tasks->reject(fn (ServiceRequest $task): bool => $recentlyNudged->contains($task->getKey()));

        if ($tasks->isEmpty()) {
            return $this->refuse($user, $place, $event, $kind, LocationNotificationReason::AlreadyNudged);
        }

        $title = (string) $place->name;
        $body = $kind->triggerMessage($direction, $tasks->count(), $title);

        // Created before delivery so the unique key reserves this entry: two
        // concurrent reports cannot both send.
        $record = LocationNotification::create([
            'place_id' => $place->getKey(),
            'user_id' => $user->getKey(),
            'geofence_event_id' => $event->getKey(),
            'status' => LocationNotificationStatus::Queued,
            'kind' => $kind->value,
            'task_count' => $tasks->count(),
            'task_ids' => $tasks->pluck('id')->all(),
            'title' => $title,
            'body' => $body,
            'idempotency_key' => $entryKey,
        ]);

        try {
            $result = $this->push->send($user, new PushMessage(
                title: $title,
                body: $body,
                style: AlertStyle::fromUser($user->alert_style),
                data: [
                    'place_id' => (string) $place->getKey(),
                    'notification_id' => (string) $record->getKey(),
                ],
                route: 'place:'.$place->getKey(),
            ));
        } catch (Throwable $exception) {
            $log = $this->logDelivery($record, $user, NotificationStatus::Failed, $title, $body, error: $exception->getMessage());

            $record->forceFill([
                'status' => LocationNotificationStatus::Failed,
                'notification_log_id' => $log->getKey(),
            ])->save();

            return $record->refresh();
        }

        $log = $this->logDelivery(
            $record,
            $user,
            NotificationStatus::Sent,
            $title,
            $body,
            provider: $result['provider'],
            messageId: $result['message_id'],
        );

        $record->forceFill([
            'status' => LocationNotificationStatus::Sent,
            'notification_log_id' => $log->getKey(),
            'notified_at' => Carbon::now(),
        ])->save();

        // Handed to the resource so the response carries the place and tasks
        // without a second query.
        return $record->refresh()
            ->setRelation('tasks', $tasks)
            ->setRelation('place', $place);
    }

    /**
     * The member's own open work that asked to be reminded by this exact cue.
     *
     * @return Collection<int, ServiceRequest>
     */
    public function triggeredTasks(User $user, Place $place, GeofenceEventType $direction): Collection
    {
        return ServiceRequest::query()
            ->where('location_trigger_place_id', $place->getKey())
            ->where('location_trigger_direction', $direction->value)
            ->where('assignee_id', $user->getKey())
            ->whereIn('status', RequestStatus::openValues())
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Request ids whose time reminder was delivered inside the nudge window.
     *
     * @param  Collection<int, ServiceRequest>  $tasks
     * @return Collection<int, int>
     */
    private function recentlyTimeNudged(User $user, Collection $tasks): Collection
    {
        $minutes = (int) config('location_notifications.nudge_window_minutes');

        if ($minutes <= 0) {
            return collect();
        }

        return NotificationLog::query()
            ->where('user_id', $user->getKey())
            ->whereIn('request_id', $tasks->pluck('id')->all())
            ->where('status', NotificationStatus::Sent->value)
            ->where('event', 'like', 'reminder.%')
            ->where('sent_at', '>=', Carbon::now()->subMinutes($minutes))
            ->pluck('request_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * A refusal still writes two rows: the decision, with its reason, and a line
     * in the member's feed so the silence is explained rather than mysterious.
     */
    private function refuse(
        User $user,
        Place $place,
        GeofenceEvent $event,
        LocationKind $kind,
        LocationNotificationReason $reason,
    ): LocationNotification {
        $key = $this->skipKey($user, $place, $event, $reason);

        $existing = LocationNotification::query()->where('idempotency_key', $key)->first();

        if ($existing !== null) {
            return $existing->setRelation('place', $place);
        }

        $record = LocationNotification::create([
            'place_id' => $place->getKey(),
            'user_id' => $user->getKey(),
            'geofence_event_id' => $event->getKey(),
            'status' => LocationNotificationStatus::Skipped,
            'reason' => $reason->value,
            'kind' => $kind->value,
            'task_count' => 0,
            'idempotency_key' => $key,
        ]);

        $log = $this->logDelivery(
            $record,
            $user,
            NotificationStatus::Skipped,
            (string) $place->name,
            $reason->sentence(),
        );

        $record->forceFill(['notification_log_id' => $log->getKey()])->save();

        return $record->refresh()->setRelation('place', $place);
    }

    private function withinCooldown(User $user, Place $place): bool
    {
        $minutes = (int) config('location_notifications.cooldown_minutes');

        if ($minutes <= 0) {
            return false;
        }

        return LocationNotification::query()
            ->where('user_id', $user->getKey())
            ->where('place_id', $place->getKey())
            ->where('status', LocationNotificationStatus::Sent->value)
            ->where('notified_at', '>=', Carbon::now()->subMinutes($minutes))
            ->exists();
    }

    private function logDelivery(
        LocationNotification $record,
        User $user,
        NotificationStatus $status,
        ?string $title,
        ?string $message,
        ?string $provider = null,
        ?string $messageId = null,
        ?string $error = null,
    ): NotificationLog {
        return NotificationLog::create([
            'tenant_id' => $record->tenant_id,
            'user_id' => $user->getKey(),
            'channel' => NotificationChannel::Push,
            'event' => 'location.arrival',
            'title' => $status === NotificationStatus::Sent ? 'Arrival' : 'Arrival not sent',
            'message' => $message,
            'status' => $status,
            'provider' => $provider,
            'provider_message_id' => $messageId,
            'error' => $error,
            'sent_at' => $status === NotificationStatus::Sent ? Carbon::now() : null,
        ]);
    }

    private function entryKey(User $user, Place $place, GeofenceEvent $event): string
    {
        return sprintf('location:%d:%d:%d', $user->getKey(), $place->getKey(), $event->getKey());
    }

    private function skipKey(
        User $user,
        Place $place,
        GeofenceEvent $event,
        LocationNotificationReason $reason,
    ): string {
        return sprintf(
            'location-skip:%d:%d:%d:%s',
            $user->getKey(),
            $place->getKey(),
            $event->getKey(),
            $reason->value,
        );
    }
}
