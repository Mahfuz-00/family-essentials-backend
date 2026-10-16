<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The runtime role needs the ops tables and — because `health:probe` and
     * `backups:run` are dispatched by the scheduler as the application — the
     * sequences too.
     */
    public function up(): void
    {
        if (! $this->roleExists('family_app')) {
            return;
        }

        foreach (['health_samples', 'incidents', 'backups'] as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }

            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$table} TO family_app");

            if ($this->sequenceExists("{$table}_id_seq")) {
                DB::statement("GRANT USAGE, SELECT ON SEQUENCE {$table}_id_seq TO family_app");
            }
        }
    }

    public function down(): void
    {
        if (! $this->roleExists('family_app')) {
            return;
        }

        foreach (['health_samples', 'incidents', 'backups'] as $table) {
            if ($this->tableExists($table)) {
                DB::statement("REVOKE ALL ON {$table} FROM family_app");
            }
        }
    }

    private function roleExists(string $role): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role]) !== null;
    }

    private function tableExists(string $table): bool
    {
        return DB::selectOne('SELECT to_regclass(?) AS name', [$table])?->name !== null;
    }

    private function sequenceExists(string $sequence): bool
    {
        return DB::selectOne('SELECT to_regclass(?) AS name', [$sequence])?->name !== null;
    }
};
