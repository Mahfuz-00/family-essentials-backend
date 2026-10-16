<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Explicitly grant the runtime role access to the audit ledger.
     *
     * The general grant migration relies on ALTER DEFAULT PRIVILEGES, which only
     * covers objects created by the role that ran it. This table is created by
     * the migration owner, so the grant is stated outright rather than assumed.
     */
    public function up(): void
    {
        if (! $this->roleExists('family_app')) {
            return;
        }

        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON audit_events TO family_app');
        DB::statement('GRANT USAGE, SELECT ON SEQUENCE audit_events_id_seq TO family_app');
    }

    public function down(): void
    {
        if (! $this->roleExists('family_app')) {
            return;
        }

        DB::statement('REVOKE ALL ON audit_events FROM family_app');
    }

    private function roleExists(string $role): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role]) !== null;
    }
};
