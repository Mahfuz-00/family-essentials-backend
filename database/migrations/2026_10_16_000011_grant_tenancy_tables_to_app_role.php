<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grant the runtime role (family_app) access to the schema.
     *
     * `ALTER DEFAULT PRIVILEGES` is per-database, so this migration makes every
     * database (including the test database, built via migrate:fresh) self
     * sufficient rather than relying on a one-off roles.sql run.
     *
     * Guarded so migrations still run on machines where the role is absent.
     */
    public function up(): void
    {
        if (! $this->roleExists('family_app')) {
            return;
        }

        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO family_app');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO family_app');

        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO family_app');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO family_app');
    }

    public function down(): void
    {
        if (! $this->roleExists('family_app')) {
            return;
        }

        DB::statement('REVOKE SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public FROM family_app');
        DB::statement('REVOKE USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public FROM family_app');
    }

    private function roleExists(string $role): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role]) !== null;
    }
};
