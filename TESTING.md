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
