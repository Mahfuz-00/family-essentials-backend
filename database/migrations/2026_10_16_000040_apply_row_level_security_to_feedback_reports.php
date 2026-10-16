<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Same barrier as every other household table: with no tenant context the
     * policy returns zero rows, so one household cannot read another's reports.
     * The platform inbox reads them with app.bypass_rls, as the landlord console
     * does everywhere else.
     */
    public function up(): void
    {
        $scope = "
            current_setting('app.bypass_rls', true) = 'on'
            OR tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::bigint
        ";

        foreach (['feedback_reports'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_isolation ON {$table} USING ({$scope}) WITH CHECK ({$scope})");
        }
    }

    public function down(): void
    {
        foreach (['feedback_reports'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
