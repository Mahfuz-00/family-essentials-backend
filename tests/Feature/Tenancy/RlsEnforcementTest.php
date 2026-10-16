<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Proves the isolation barrier lives in PostgreSQL itself, not just in Eloquent.
 */
class RlsEnforcementTest extends TestCase
{
    use CreatesTenants;

    public function test_rls_denies_everything_without_a_tenant_context(): void
    {
        $tenant = $this->createTenant('Household A');
        $this->createMember($tenant);

        $this->assertSame(0, DB::connection('pgsql')->table('tenant_users')->count());
        $this->assertSame(0, DB::connection('pgsql')->table('tenants')->count());
    }

    public function test_rls_only_reveals_rows_for_the_current_tenant(): void
    {
        $tenantA = $this->createTenant('Household A');
        $this->createMember($tenantA);

        $tenantB = $this->createTenant('Household B');
        $this->createMember($tenantB);

        DB::connection('pgsql')->statement(
            "select set_config('app.current_tenant', ?, false)",
            [(string) $tenantA->id],
        );
        DB::connection('pgsql')->statement("select set_config('app.bypass_rls', 'off', false)");

        $this->assertSame(1, DB::connection('pgsql')->table('tenant_users')->count());
        $this->assertSame((int) $tenantA->id, (int) DB::connection('pgsql')->table('tenants')->value('id'));
    }

    public function test_rls_blocks_writing_rows_for_another_tenant(): void
    {
        $tenantA = $this->createTenant('Household A');
        $this->createMember($tenantA);

        $tenantB = $this->createTenant('Household B');
        $foreignMember = $this->createMember($tenantB);

        $connection = DB::connection('pgsql');
        $connection->statement("select set_config('app.current_tenant', ?, false)", [(string) $tenantA->id]);
        $connection->statement("select set_config('app.bypass_rls', 'off', false)");

        $connection->beginTransaction();

        try {
            $connection->table('tenant_users')->insert([
                'tenant_id' => $tenantB->id,
                'user_id' => $foreignMember->id,
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('Expected PostgreSQL to reject the cross-tenant insert.');
        } catch (QueryException $exception) {
            $this->assertStringContainsStringIgnoringCase('row-level security', $exception->getMessage());
        } finally {
            $connection->rollBack();
        }
    }
}
