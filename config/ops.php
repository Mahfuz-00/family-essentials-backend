<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Operational thresholds
    |--------------------------------------------------------------------------
    |
    | The health report classifies each check against these. They are the
    | difference between a green light that means something and one that is
    | decoration.
    |
    */

    'thresholds' => [
        // Milliseconds for a trivial round trip.
        'database_latency_warn_ms' => (int) env('OPS_DB_LATENCY_WARN_MS', 250),
        'database_latency_fail_ms' => (int) env('OPS_DB_LATENCY_FAIL_MS', 1000),

        // Queued jobs waiting.
        'queue_backlog_warn' => (int) env('OPS_QUEUE_BACKLOG_WARN', 100),
        'queue_backlog_fail' => (int) env('OPS_QUEUE_BACKLOG_FAIL', 5000),

        // Minutes since the scheduler last reported.
        'scheduler_warn_minutes' => (int) env('OPS_SCHEDULER_WARN_MINUTES', 5),
        'scheduler_fail_minutes' => (int) env('OPS_SCHEDULER_FAIL_MINUTES', 30),

        // Free disk space on the storage volume.
        'disk_free_warn_percent' => (int) env('OPS_DISK_FREE_WARN_PERCENT', 15),
        'disk_free_fail_percent' => (int) env('OPS_DISK_FREE_FAIL_PERCENT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telemetry retention
    |--------------------------------------------------------------------------
    */

    'sample_interval_minutes' => (int) env('OPS_SAMPLE_INTERVAL_MINUTES', 5),
    'sample_retention_days' => (int) env('OPS_SAMPLE_RETENTION_DAYS', 30),

    /*
    | The scheduler heartbeat lives in the cache rather than a table: it is a
    | single moving value, and it must survive a dashboard restart to be useful.
    */
    'heartbeat_key' => 'ops:scheduler:heartbeat',

];
