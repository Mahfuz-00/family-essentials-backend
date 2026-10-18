# Testing

## Layers

Tests are split by **scope**, in three directories. `phpunit.xml` registers each
one as its own suite, so you can run a layer on its own.

| Directory | Scope | Belongs here |
|---|---|---|
| `tests/Unit` | One class, no I/O | Pure rules and algorithms — geometry, date arithmetic, the pantry due rule. No HTTP, no routes, no fixtures beyond an unsaved model. |
| `tests/Feature` | One endpoint group, one user-facing behaviour | "A member can create a place", "a junior cannot dispatch a request", "the ledger exports as CSV". Drives HTTP and asserts on responses. |
| `tests/Integration` | Several components together | Things that only fail when the parts are wired up: the PostgreSQL row-level-security barrier, cross-tenant isolation, the audit ledger, and the dashboard/mobile surface split. |

Rule of thumb: if it needs `$this->getJson(...)`, it is Feature or Integration.
If it needs the database *engine* to be the thing under test, it is Integration.

```
php artisan test                      # everything
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
php artisan test --filter=PlaceCrudTest
```

## Databases — the development data is never touched

The suite calls `migrate:fresh`, which **drops every table**. Two things keep the
development database safe:

1. `phpunit.xml` sets `DB_DATABASE=family_essentials_test`.
2. `Tests\TestCase::guardAgainstDevelopmentDatabase()` refuses to start if either
   connection (`pgsql` or `pgsql_admin`) points at a database whose name does not
   end in `_test`. This runs before `migrate:fresh`, so a misconfigured run fails
   loudly instead of destroying data.

`family_essentials` (development) and `family_essentials_test` are separate
databases. If the test database is missing, create it once:

```sql
CREATE DATABASE family_essentials_test OWNER postgres;
```

Migrations themselves need the owner connection, because the runtime role
(`family_app`) deliberately cannot create tables:

```
php artisan migrate --database=pgsql_admin          # development
```

Tests do the same automatically.

Each test also runs inside a transaction that is rolled back in `tearDown`, so
tests are isolated from one another.

## Operations tests

The landlord console has its own suites, and they assert on measurements rather
than on the existence of a page:

| Test | What it pins down |
|---|---|
| `tests/Feature/Platform/SystemHealthTest.php` | Every check the report claims appears with a real status and message; the overall status is the worst check; uptime comes from samples and is null when there are none; the probe command samples and prunes; incidents open, resolve and re-open. |
| `tests/Feature/Platform/BackupTest.php` | A completed run records a real file and its checksum; a run with no dump tool is `skipped`, never faked; verification notices a tampered file; pruning removes the file as well as the row; readiness states the gaps instead of claiming readiness. |

`BackupTest` uses a `FakeDumpRunner` for all of that, so the suite is hermetic and
takes seconds. One test exercises the real `pg_dump`, and it is **opt-in** — a child
process started from inside PHPUnit on Windows can wedge the run:

```
BACKUP_REAL_DUMP_TEST=1 php artisan test --filter=test_a_real_pg_dump_produces_a_restorable_file
```

To confirm the real path by hand instead:

```
php artisan backups:run --type=manual     # dumps, then verifies and prints the result
php artisan backups:verify
```

The dump is taken as `BACKUP_DB_CONNECTION` (`pgsql_admin`), **not** the runtime
role: every tenant table is `FORCE ROW LEVEL SECURITY`, so a dump as `family_app`
would export zero rows and look successful. `backups:run` needs no special flags
to prove this — `readiness.connection_is_owner` reports it, and a test asserts the
warning fires if the two are ever set to the same connection.

## Manual QA

`php artisan db:seed` creates two isolated households plus a landlord account.
All seeded accounts use the password **`password`** — development fixtures only,
never for anything public.

| Email | Password | Role | Where they belong |
|---|---|---|---|
| `tanvir@familyessentials.app` | `password` | Tenant admin | The Tanvir Household (household 1) |
| `sarah@familyessentials.app` | `password` | Member | The Tanvir Household (household 1) |
| `bradley@familyessentials.app` | `password` | Tenant admin | The Bradley Household (household 2) |
| `elena@familyessentials.app` | `password` | Member | The Bradley Household (household 2) |
| `platform@familyessentials.app` | `password` | Platform admin | All tenants (landlord console) |

Two households that cannot see each other's data is deliberate: signing in as
`tanvir` and `bradley` is how the isolation boundary is checked by hand.

The web dashboard signs in through `/api/v1`, the Flutter app through
`/mobile/v1`. A token minted on one surface is rejected by the other, so use the
matching app for each.

### What `db:seed` puts in the database

`DatabaseSeeder` creates the accounts above; `DemoDataSeeder` then fills both
households so every screen has something to show. Both are **safe to run again**
— each row is looked up before it is created, and the demo rows pin `tenant_id`
explicitly because `db:seed` suppresses model events.

| | The Tanvir Household | The Bradley Household |
|---|---|---|
| Saved places | Home, Office, Whole Foods Market, CVS Pharmacy, School Gate | Home, Office, Tesco Express |
| Requests | 11 across every board column, including overdue, due today, backlog, completed today, one with free-text `store_name` only and one with no location | 3 (one overdue, one due today, one backlog) |
| Pantry | 5 essentials — two due (one by safety stock, one by cadence), one healthy, one inactive | 2 |
| Feedback | a new bug, a triaged feature request, a resolved one with a note | a new feature request |
| Device tokens | one iOS, one Android | one Android |
| Arrival notifications | **opted in** (Tanvir), off (Sarah) | **opted in** (Bradley), off (Elena) |

Both admins are opted in so arrival notifications can be demonstrated without
changing a setting first; the other two members are opted out, which is how you
see the refusal path.

### Demonstrating arrival notifications

The seeded work is shaped for it — Tanvir has open tasks at Home, Office, Whole
Foods and School Gate:

| Place | Open tasks | What the notification says |
|---|---|---|
| Home | 1 | "You have 1 household task to take care of." |
| Office | 1 | "You have 1 task associated with work." |
| Whole Foods Market | 2 | "You have 2 items to buy here." |
| School Gate | 1 | "You have 1 task here." |

In the app, Today → **Simulate arriving at Whole Foods Market**. The first
arrival sends one notification; arriving again immediately is recorded as a
`cooldown` refusal. The same arrival as Sarah is recorded as `not_opted_in`.

Do not send a test arrival before you want to demo one — it starts the cooldown
(`LOCATION_NOTIFICATION_COOLDOWN_MINUTES`, 60 by default).
