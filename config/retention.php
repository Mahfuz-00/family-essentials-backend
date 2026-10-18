<?php

return [

    /*
    |--------------------------------------------------------------------------
    | History retention
    |--------------------------------------------------------------------------
    |
    | Some tables only ever grow: one row per position report, per arrival
    | decision, per notification we sent, per call we placed. `essentials:prune-history`
    | is what stops them being the reason the database needs a bigger machine,
    | and this file is the whole policy it applies — the command itself holds no
    | table list of its own.
    |
    | Each entry names the timestamp the window is measured back from and how
    | many days of history to keep. `days => null` means "keep forever", and a
    | table that is not listed here is never touched at all.
    |
    */

    'batch_size' => (int) env('RETENTION_BATCH_SIZE', 1000),

    'tables' => [

        // Location history is the highest-volume, lowest-value data in the
        // schema: a position report is interesting for as long as somebody may
        // ask "was she at school this morning?", and no longer.
        'geofence_events' => [
            'column' => 'occurred_at',
            'days' => env('RETENTION_GEOFENCE_EVENTS_DAYS', 90),
        ],

        // Arrival decisions, kept longer than the raw events they came from:
        // "why was I not told?" is a question asked weeks later.
        'location_notifications' => [
            'column' => 'created_at',
            'days' => env('RETENTION_LOCATION_NOTIFICATIONS_DAYS', 180),
        ],

        // The delivery feed. Same horizon as the decisions it records.
        'notification_logs' => [
            'column' => 'created_at',
            'days' => env('RETENTION_NOTIFICATION_LOGS_DAYS', 180),
        ],

        // Voice escalation is billable and disputable, so it is kept for a year.
        'voice_call_logs' => [
            'column' => 'created_at',
            'days' => env('RETENTION_VOICE_CALL_LOGS_DAYS', 365),
        ],

        /*
        | Both of these are ledgers rather than logs, so both default to
        | `null` — kept forever. That default is deliberate and load-bearing:
        |
        |   * `audit_events` is the platform's security record. A scheduled job
        |     must never delete the evidence that privileged actions happened
        |     because a retention default happened to be convenient; an operator
        |     has to ask for it, by setting RETENTION_AUDIT_EVENTS_DAYS.
        |   * `feedback_reports` is what households took the trouble to tell us,
        |     and a resolved report is the product's own history.
        |
        | Setting either env var to a number of days opts that table in.
        */
        'feedback_reports' => [
            'column' => 'created_at',
            'days' => env('RETENTION_FEEDBACK_REPORTS_DAYS'),
        ],

        'audit_events' => [
            'column' => 'occurred_at',
            'days' => env('RETENTION_AUDIT_EVENTS_DAYS'),
        ],
    ],

];
