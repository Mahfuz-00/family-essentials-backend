<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Arrival notifications
    |--------------------------------------------------------------------------
    |
    | A member is told about work waiting where they have just arrived. The rules
    | live server-side (see App\Geo\LocationNotifier) so the app cannot decide for
    | itself who is told what.
    |
    */

    'enabled' => env('LOCATION_NOTIFICATIONS_ENABLED', true),

    /*
    | How long to stay quiet about the same place after telling somebody once.
    | Leaving and coming straight back — the school run, two trips to the car —
    | must not produce a second notification.
    */
    'cooldown_minutes' => (int) env('LOCATION_NOTIFICATION_COOLDOWN_MINUTES', 60),

    /*
    | One nudge, not two.
    |
    | A location notification is suppressed for tasks whose time reminder was
    | delivered inside this window. One-directional on purpose: the deadline
    | reminder is never suppressed by a location nudge.
    */
    'nudge_window_minutes' => (int) env('LOCATION_NUDGE_WINDOW_MINUTES', 120),

    /*
    | A recorded entry older than this stops counting as "still here".
    |
    | Dwell state is derived from the last geofence event, and a client that stops
    | reporting never writes the exit. Without this cutoff an entry from days ago
    | would read as still-inside, so the next visit would produce no arrival at
    | all — the feature would silently do nothing on the second trip.
    */
    'stale_dwell_hours' => (int) env('LOCATION_NOTIFICATION_STALE_DWELL_HOURS', 12),

];
