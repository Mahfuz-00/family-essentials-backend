# Family Essentials — API

Laravel 13 on PHP 8.4, backed by PostgreSQL 16. One application, two client
surfaces, one tenant model.

| Surface | Prefix | Token ability | Clients |
|---|---|---|---|
| Dashboard | `/api/v1` | `dashboard` | The React dashboard, and the platform admin's landlord console |
| Mobile | `/mobile/v1` | `mobile` | The Flutter app |
| Landlord console | `/api/v1/platform/*` | `dashboard` + `platform.admin` | Dashboard only — deliberately absent from mobile |

The surfaces share controllers (`app/Http/Controllers/Api/V1/*`) but not
credentials: a token minted by one is rejected by the other. **A change to a
shared controller usually belongs in both `routes/api.php` and
`routes/mobile.php`** — check parity before you finish.

## Requirements

- PHP 8.4 with the `pgsql` extension
- Composer
- PostgreSQL 16 (the client tools too, if you want backups: `pg_dump`)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Two databases: development, and one the tests can destroy.
#   CREATE DATABASE family_essentials OWNER postgres;
#   CREATE DATABASE family_essentials_test OWNER postgres;

php artisan migrate --database=pgsql_admin --force
php artisan db:seed
```

### Two database roles, on purpose

| Role | Used for | Can |
|---|---|---|
| `family_app` (`DB_USERNAME`) | Everything at runtime | Read and write rows *within one tenant* |
| `postgres` / `pgsql_admin` (`DB_ADMIN_*`) | Migrations and backups | Create tables, and see every row |

The runtime role is not the owner and not a superuser, which is what makes
`FORCE ROW LEVEL SECURITY` apply to it. Adding a household table therefore takes
**three** migrations: the table, its RLS policy (`ENABLE` + `FORCE` + policy), and
the grants. Skipping the second produces a table that quietly ignores tenant
context.

## Running

```bash
php artisan serve            # http://localhost:8000
php artisan schedule:work     # reminders, voice escalation, health samples, backups
php artisan queue:work        # queued work (the queue driver is `database`)
```

| Endpoint | What it is |
|---|---|
| `GET /` | A discovery document listing both surfaces and their abilities |
| `GET /up` | Liveness |

Without the scheduler the health console correctly reports a `warn` on the
`scheduler` check.

## Sources of truth

Three config files are read by both the enforcement and the interface, which is
what stops them drifting apart:

| File | Declares | Read by |
|---|---|---|
| `config/roles.php` | Role tiers and capabilities | `TenantUserPolicy`, and `GET /v1/tenant/roles` — the matrix the dashboard renders |
| `config/plans.php` | Tier keys, seat / place / monthly-request caps | `Plans\QuotaService`, `GET /v1/tenant/quotas`, tier validation |
| `config/voice.php` | Daily call budget, attempt limit, cooldown | `Voice\VoiceEscalationPolicy` and the escalation screen |

`config/ops.php` and `config/backup.php` hold the operational thresholds and
paths.

## The rules

1. **Isolation is a database property.** `App\Tenancy\TenantContext` holds the
   active tenant and a bypass flag; `TenantGucs` mirrors them into the connection;
   `BelongsToTenant` applies the global scope and fails closed.
2. **Migrations run as the owner:**
   `php artisan migrate --database=pgsql_admin`. The tests do the same.
3. **Every privileged action is audited** through `App\Audit\AuditRecorder` into
   the append-only `audit_events` ledger. Recording is best-effort and
   savepoint-safe — an audit failure must never break the request it observes.
4. **Nothing green that was not measured.** `Ops\HealthReport` fails a check it
   cannot run; a backup with no dump tool is recorded as `skipped` with the reason.
5. **Provider seams.** SMS, push, voice, geofencing and the dump tool each sit
   behind an interface bound in `AppServiceProvider` with a log implementation as
   the default, so a fresh clone runs with no credentials.

## Tests

```bash
php artisan test
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature --filter=QuotaTest
```

Roughly 250 passing with one skipped (the opt-in real `pg_dump` path, which is
documented in [TESTING.md](./TESTING.md) along with the layers, the database
guard, and the seeded accounts for manual QA).

## Operations

```bash
php artisan ops:health-probe            # sample the system now (and prune old samples)
php artisan ops:heartbeat               # stamp the scheduler
php artisan backups:run --type=manual   # dump the database, then verify it
php artisan backups:verify              # re-checksum unverified backups
php artisan backups:prune               # retention, and reap interrupted runs
php artisan schedule:list               # what runs on its own, and when
```

Full procedures — the health checks and their thresholds, incident handling,
feedback triage, tenancy administration, and the deploy checklist — are in
`Docs/operations.md`, with the restore procedure in
`Docs/disaster-recovery-runbook.md`.

## Documentation

This repository carries its own `README.md` and `TESTING.md`. The cross-cutting
documents (PRD, architecture, design system, API reference, operations) live in
the monorepo root under `Docs/`, which is not part of this repository.
