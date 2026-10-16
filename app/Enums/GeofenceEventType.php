<?php

namespace App\Enums;

enum GeofenceEventType: string
{
    case Enter = 'enter';
    case Exit = 'exit';
}
