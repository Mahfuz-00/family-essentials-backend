<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Row-Level Security for the Phase 4 reminder / notification tables - the
     * same barrier as the tenancy and workflow tables.
     */
    public function up(): void
    {
        $scope = "
            current_setting('app.bypass_rls', true) = 'on'
            OR tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::bigint
        ";

        foreach (['reminders', 'device_tokens', 'notification_logs'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_isolation ON {$table} USING ({$scope}) WITH CHECK ({$scope})");
        }
    }

    public function down(): void
    {
        foreach (['reminders', 'device_tokens', 'notification_logs'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
