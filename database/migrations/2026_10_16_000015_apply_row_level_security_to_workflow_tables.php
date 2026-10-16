<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Row-Level Security for the Phase 3 workflow tables - same barrier as the
     * Phase 2 tenancy tables (see 2026_10_16_000010_apply_row_level_security).
     */
    public function up(): void
    {
        $scope = "
            current_setting('app.bypass_rls', true) = 'on'
            OR tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::bigint
        ";

        foreach (['items', 'requests', 'assignments'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_isolation ON {$table} USING ({$scope}) WITH CHECK ({$scope})");
        }
    }

    public function down(): void
    {
        foreach (['items', 'requests', 'assignments'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
