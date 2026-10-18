<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Person-level isolation, on top of the tenant barrier.
     *
     * Every other table in this schema answers "which household?". These three
     * also answer "which person?", because a household ledger is not household
     * property: a member sees the ledgers they were added to and nothing else.
     * The person comes from `app.current_user`, set next to `app.current_tenant`
     * (see App\Tenancy\TenantGucs), and an unset GUC matches no member row — so
     * a request without a person reads zero ledger rows, exactly as a request
     * without a household reads zero tenant rows.
     *
     * The policies are deliberately shallow and never back-reference a table
     * that is itself policy-protected in a way that could recurse:
     *
     *   ledger_members -> its own user_id only (never `ledgers`)
     *   ledgers        -> EXISTS over ledger_members
     *   ledger_entries -> EXISTS over ledger_members
     *
     * `ledgers` additionally lets a caller read and insert a ledger they own
     * before any membership row exists: creating the ledger and its first
     * membership is one transaction, so a member-only policy would make the
     * ledger uncreatable. (PostgreSQL checks `INSERT ... RETURNING` — which is
     * how Eloquent reads the new id back — against the SELECT policy as well, so
     * the owner clause has to appear on both sides.) The row is always created
     * with owner_id = the caller.
     */
    public function up(): void
    {
        $bypass = "current_setting('app.bypass_rls', true) = 'on'";
        $user = "NULLIF(current_setting('app.current_user', true), '')::bigint";

        // The person's own membership row, and only that row. Referencing
        // `ledgers` here would recurse (ledgers -> ledger_members -> ledgers).
        $memberScope = "{$bypass} OR user_id = {$user}";
        DB::statement("ALTER TABLE ledger_members ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE ledger_members FORCE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY member_isolation ON ledger_members USING ({$memberScope}) WITH CHECK ({$memberScope})");

        $memberOfLedger = "EXISTS (
            SELECT 1 FROM ledger_members m
            WHERE m.ledger_id = ledgers.id AND m.user_id = {$user}
        )";
        $ledgerScope = "{$bypass} OR owner_id = {$user} OR {$memberOfLedger}";
        DB::statement("ALTER TABLE ledgers ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE ledgers FORCE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY member_isolation ON ledgers USING ({$ledgerScope}) WITH CHECK ({$ledgerScope})");

        $memberOfEntryLedger = "EXISTS (
            SELECT 1 FROM ledger_members m
            WHERE m.ledger_id = ledger_entries.ledger_id AND m.user_id = {$user}
        )";
        DB::statement("ALTER TABLE ledger_entries ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE ledger_entries FORCE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY member_isolation ON ledger_entries USING ({$bypass} OR {$memberOfEntryLedger}) WITH CHECK ({$bypass} OR {$memberOfEntryLedger})");
    }

    public function down(): void
    {
        foreach (['ledger_entries', 'ledgers', 'ledger_members'] as $table) {
            DB::statement("DROP POLICY IF EXISTS member_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
