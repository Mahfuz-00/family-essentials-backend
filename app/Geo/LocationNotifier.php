<?php

namespace App\Geo;

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
use App\Reminders\QuietHours;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Tells a member about the work waiting where they have just arrived.
 *
 * Runs from {@see GeofenceMonitor} on an entry transition, which is already the
 * only moment "arrival" can be observed: the evaluator emits a transition when
 * the inside/outside state *changes*, so standing still produces nothing.
 *
 * Three rules govern everything here:
 *
 *  1. One decision per recorded entry, enforced by a unique idempotency key, so a
 *     repeated position report cannot notify twice.
 *  2. A refusal is recorded with its reason, never silently dropped — the voice
 *     policy's rule, applied to arrivals.
 *  3. Only the reader's own open work counts. The store shopping list is
 *     deliberately broader (everyone's open requests at that place); a
 *     notification is personal, so it is narrowed to the assignee.
 */
class LocationNotifier
{
    public function __construct(
        private readonly PushSender $push,
        private readonly QuietHours $quietHours,
    ) {}

    public function onEnter(User $user, Place $place, GeofenceEvent $event): LocationNotification
    {
        $kind = $place->kind();

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

        $tasks = $this->waitingTasks($user, $place);

        if ($tasks->isEmpty()) {
            return $this->refuse($user, $place, $event, $kind, LocationNotificationReason::NoTasks);
        }

        // The place's own name is the title: "Whole Foods", "Home". The body is
        // the one sentence the kind decides.
        $title = (string) $place->name;
        $body = $kind->arrivalMessage($tasks->count());

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
            $result = $this->push->send($user, $title, $body);
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
     * The member's own open work at this place, ordered as they would work it.
     *
     * @return Collection<int, ServiceRequest>
     */
    public function waitingTasks(User $user, Place $place): Collection
    {
        return ServiceRequest::query()
            ->where('place_id', $place->getKey())
            ->where('assignee_id', $user->getKey())
            ->whereIn('status', RequestStatus::openValues())
            ->orderBy('due_at')
            ->get();
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
            return $existing;
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
