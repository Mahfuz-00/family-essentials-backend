<?php

namespace App\Enums;

/**
 * Why an arrival produced no notification.
 *
 * Every refusal is recorded with its reason — the same rule the voice policy
 * follows — so "why was I not told?" is answerable from the data.
 */
enum LocationNotificationReason: string
{
    case Disabled = 'disabled';
    case NotOptedIn = 'not_opted_in';
    case QuietHours = 'quiet_hours';
    case Cooldown = 'cooldown';
    case NoTasks = 'no_tasks';
    case AlreadyNudged = 'already_nudged';

    /**
     * The member-facing explanation, shown in their notification feed.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::Disabled => 'Location notifications are switched off for this household.',
            self::NotOptedIn => 'Location notifications are off for you — turn them on in Settings.',
            self::QuietHours => 'Held back during household quiet hours.',
            self::Cooldown => 'You were already told about this place recently.',
            self::NoTasks => 'Nothing of yours is waiting here.',
            self::AlreadyNudged => 'You were reminded about this a few minutes ago.',
        };
    }
}
