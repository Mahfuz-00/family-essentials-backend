<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Widen the household role list with the two tiers the UI exposes:
     * `co_admin` (runs the household, cannot change roles) and `junior`
     * (raises requests, cannot dispatch or manage places).
     *
     * Handles both shapes Laravel can produce on PostgreSQL: a varchar with a
     * CHECK constraint (the default) and a native enum type.
     */
    public function up(): void
    {
        $this->widen('tenant_users', "'tenant_admin','co_admin','member','junior'");
        $this->widen('tenant_invitations', "'tenant_admin','co_admin','member','junior'");
    }

    public function down(): void
    {
        $this->widen('tenant_users', "'tenant_admin','member'");
        $this->widen('tenant_invitations', "'tenant_admin','member'");
    }

    private function widen(string $table, string $values): void
    {
        $enum = DB::selectOne(
            "SELECT t.typname
               FROM pg_type t
               JOIN pg_attribute a ON a.atttypid = t.oid
              WHERE a.attrelid = '{$table}'::regclass
                AND a.attname = 'role'
                AND t.typtype = 'e'"
        );

        if ($enum !== null) {
            foreach (['co_admin', 'junior'] as $value) {
                DB::statement("ALTER TYPE {$enum->typname} ADD VALUE IF NOT EXISTS '{$value}'");
            }

            return;
        }

        $constraint = DB::selectOne(
            "SELECT conname
               FROM pg_constraint
              WHERE conrelid = '{$table}'::regclass
                AND contype = 'c'
                AND pg_get_constraintdef(oid) LIKE '%tenant_admin%'"
        );

        if ($constraint !== null) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint->conname}");
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_role_check CHECK (role IN ({$values}))");
    }
};
