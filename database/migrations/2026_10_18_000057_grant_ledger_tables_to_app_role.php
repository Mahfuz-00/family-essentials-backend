<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The runtime role needs the tables and their sequences. The policies above
     * are what decide which rows it may actually touch.
     */
    private const TABLES = ['ledgers', 'ledger_members', 'ledger_entries'];

    public function up(): void
    {
        if (! $this->roleExists('family_app')) {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$table} TO family_app");

            if ($this->tableExists("{$table}_id_seq")) {
                DB::statement("GRANT USAGE, SELECT ON SEQUENCE {$table}_id_seq TO family_app");
            }
        }
    }

    public function down(): void
    {
        if (! $this->roleExists('family_app')) {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::statement("REVOKE ALL ON {$table} FROM family_app");
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
