<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Local = 'local';
    case Push = 'push';
    case Voice = 'voice';
}
