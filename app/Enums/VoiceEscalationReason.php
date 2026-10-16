<?php

namespace App\Enums;

/**
 * Why a call was placed, or why one was refused. Refusals are always recorded
 * so nothing is silently dropped.
 */
enum VoiceEscalationReason: string
{
    case Overdue = 'overdue';
    case Manual = 'manual';

    case OptOut = 'opt_out';
    case NoPhone = 'no_phone';
    case LowPriority = 'low_priority';
    case QuietHours = 'quiet_hours';
    case RetryLimit = 'retry_limit';
    case AlreadyClosed = 'already_closed';
}
