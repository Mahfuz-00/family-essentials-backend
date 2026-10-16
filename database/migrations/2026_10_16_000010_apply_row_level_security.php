<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PostgreSQL Row-Level Security — the "second hard barrier" from PRD §9.
     *
     * Two per-request GUCs drive the policies:
     *   app.current_tenant  -> the active tenant id (set by IdentifyTenant)
     *   app.bypass_rls      -> 'on' for Platform Admin / system provisioning
     *
     * When neither is set, current_setting(..., true) returns NULL and the
     * policies deny everything (default deny). RLS is enforced because the
     * runtime role (family_app) is neither the owner nor a superuser.
     */
    public function up(): void
    {
        $scope = "
            current_setting('app.bypass_rls', true) = 'on'
            OR tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::bigint
        ";

        foreach (['tenant_users', 'tenant_invitations'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_isolation ON {$table} USING ({$scope}) WITH CHECK ({$scope})");
        }

        $tenantScope = "
            current_setting('app.bypass_rls', true) = 'on'
            OR id = NULLIF(current_setting('app.current_tenant', true), '')::bigint
        ";

        DB::statement('ALTER TABLE tenants ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenants FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_scope ON tenants USING ({$tenantScope}) WITH CHECK ({$tenantScope})");
    }

    public function down(): void
    {
        foreach (['tenant_users', 'tenant_invitations'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }

        DB::statement('DROP POLICY IF EXISTS tenant_scope ON tenants');
        DB::statement('ALTER TABLE tenants NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenants DISABLE ROW LEVEL SECURITY');
    }
};
