<?php

namespace App\Enums;

enum AssignmentAction: string
{
    case Assigned = 'assigned';
    case Reassigned = 'reassigned';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Started = 'started';
    case Completed = 'completed';
    case Unavailable = 'unavailable';
    case Cancelled = 'cancelled';
}
