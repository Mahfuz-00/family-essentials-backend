<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Which connection a backup is taken as
    |--------------------------------------------------------------------------
    |
    | This must NOT be the runtime role. Every tenant table carries
    | `FORCE ROW LEVEL SECURITY`, so a dump taken as `family_app` with no
    | `app.current_tenant` set would silently contain zero rows — a backup that
    | looks successful and restores to an empty database. The owner connection
    | sees every row, which is what a backup needs.
    |
    */

    'connection' => env('BACKUP_DB_CONNECTION', 'pgsql_admin'),

    /*
    |--------------------------------------------------------------------------
    | Where dumps go, and for how long
    |--------------------------------------------------------------------------
    */

    'disk' => env('BACKUP_DISK', 'local'),
    'directory' => env('BACKUP_DIRECTORY', 'backups'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'daily_at' => env('BACKUP_DAILY_AT', '02:30'),

    /*
    |--------------------------------------------------------------------------
    | Recovery objectives
    |--------------------------------------------------------------------------
    |
    | Declared so the console can compare them against reality instead of
    | asserting readiness. `rpo` is in hours; `rto` is in minutes.
    |
    */

    'rpo_hours' => (int) env('BACKUP_RPO_HOURS', 24),
    'rto_minutes' => (int) env('BACKUP_RTO_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | pg_dump
    |--------------------------------------------------------------------------
    |
    | The custom format is already compressed and restorable with pg_restore,
    | so no extra gzip step is involved.
    |
    */

    'pg_dump_binary' => env('BACKUP_PG_DUMP', 'pg_dump'),
    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 1800),

];
