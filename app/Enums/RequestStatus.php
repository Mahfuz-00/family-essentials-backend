<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Unavailable = 'unavailable';

    /**
     * A request still needing work (reminders may fire for these).
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Accepted, self::InProgress], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Declined, self::Cancelled, self::Unavailable], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Unavailable => 'Unavailable',
        };
    }
}
