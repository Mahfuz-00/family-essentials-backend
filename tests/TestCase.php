<?php

namespace Tests;

use App\Tenancy\Tenancy;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantGucs;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private bool $transactionOpen = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstDevelopmentDatabase();
        $this->migrateDatabaseOnce();

        // The suite runs against the runtime role (family_app) so PostgreSQL RLS
        // is genuinely exercised. Each test is wrapped in a transaction.
        DB::connection('pgsql')->beginTransaction();
        $this->transactionOpen = true;
    }

    /**
     * The suite calls `migrate:fresh`, which drops every table, so it must never
     * be pointed at the development database. phpunit.xml sets the test database,
     * and this guard makes that a hard requirement rather than a convention.
     */
    private function guardAgainstDevelopmentDatabase(): void
    {
        $database = (string) config('database.connections.pgsql.database');
        $admin = (string) config('database.connections.pgsql_admin.database');

        foreach (['pgsql' => $database, 'pgsql_admin' => $admin] as $connection => $name) {
            if (! str_ends_with($name, '_test')) {
                throw new RuntimeException(
                    "Refusing to run tests: connection [{$connection}] points at [{$name}]. "
                    .'The test database name must end with "_test" so the development data is never dropped.'
                );
            }
        }
    }

    protected function tearDown(): void
    {
        if ($this->transactionOpen) {
            // Clear any tenant GUCs left set by raw statements in a test.
            $context = app(TenantContext::class);
            $context->reset();
            TenantGucs::apply($context);

            DB::connection('pgsql')->rollBack();
            $this->transactionOpen = false;
        }

        parent::tearDown();
    }

    /**
     * Sanctum's RequestGuard caches the resolved user against its original
     * request, so a second request in the same test would keep the first user.
     * Clearing the guards makes each request authenticate with its own token.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * Schema creation needs the owner connection; the runtime role cannot create
     * tables. Runs once per PHPUnit process.
     */
    private function migrateDatabaseOnce(): void
    {
        static $migrated = false;

        if ($migrated) {
            return;
        }

        $this->artisan('migrate:fresh', [
            '--database' => 'pgsql_admin',
            '--force' => true,
        ])->run();

        $migrated = true;
    }

    /**
     * Run a callback inside a specific tenant context (for background-style code).
     */
    protected function asTenant(int $tenantId, callable $callback): mixed
    {
        return Tenancy::forTenant($tenantId, $callback);
    }

    /**
     * Run a callback with the RLS bypass enabled (on the runtime connection) so
     * tests can assert against tenant-scoped tables.
     */
    protected function withBypass(callable $callback): mixed
    {
        $context = app(TenantContext::class);
        $previousId = $context->id();
        $previousUser = $context->userId();
        $previousBypass = $context->isBypassed();

        $context->bypass(true);
        TenantGucs::apply($context);

        try {
            return $callback();
        } finally {
            // Restore the previous context (not a full reset) so this composes
            // with an enclosing tenant scope.
            $context->reset();
            $context->set($previousId);
            $context->setUser($previousUser);
            $context->bypass($previousBypass);
            TenantGucs::apply($context);
        }
    }
}
