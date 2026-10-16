<?php

namespace App\Enums;

enum VoiceCallStatus: string
{
    case Queued = 'queued';
    case Ringing = 'ringing';
    case Answered = 'answered';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Completed = 'completed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::NoAnswer, self::Busy, self::Failed, self::Skipped, self::Completed], true);
    }
}
