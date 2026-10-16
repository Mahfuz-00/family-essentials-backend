<?php

namespace App\Enums;

enum ReminderType: string
{
    case Due = 'due';
    case Snooze = 'snooze';
}
