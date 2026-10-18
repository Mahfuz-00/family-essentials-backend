<?php

namespace App\Enums;

enum LocationNotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
