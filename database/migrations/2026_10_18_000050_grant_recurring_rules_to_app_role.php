<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The runtime role needs the table and its sequence.
     */
    public function up(): void
    {
        if (! $this->roleExists('family_app')) {
            return;
        }

        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON recurring_rules TO family_app');

        if ($this->tableExists('recurring_rules_id_seq')) {
            DB::statement('GRANT USAGE, SELECT ON SEQUENCE recurring_rules_id_seq TO family_app');
        }
    }

    public function down(): void
    {
        if ($this->roleExists('family_app')) {
            DB::statement('REVOKE ALL ON recurring_rules FROM family_app');
        }
    }

    private function roleExists(string $role): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role]) !== null;
    }

    private function tableExists(string $name): bool
    {
        return DB::selectOne('SELECT to_regclass(?) AS name', [$name])?->name !== null;
    }
};
