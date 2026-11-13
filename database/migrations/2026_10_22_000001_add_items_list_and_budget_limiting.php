<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Budget Limiting on Ledgers
        Schema::table('ledgers', function (Blueprint $table) {
            $table->bigInteger('monthly_budget_minor')->nullable()->after('currency');
            $table->bigInteger('weekly_budget_minor')->nullable()->after('monthly_budget_minor');
        });

        // 2. Multi-item list and dual quantities on Requests
        Schema::table('requests', function (Blueprint $table) {
            $table->jsonb('items')->default('[]')->after('quantity');
            $table->string('quantity_got', 64)->nullable()->after('items');
        });

        // 3. Multi-item list and direction on Recurring Chores / Recurring Expenses
        Schema::table('recurring_rules', function (Blueprint $table) {
            $table->jsonb('items')->default('[]')->after('quantity');
            $table->string('direction', 16)->default('expense')->after('cadence');
        });

        // 4. Allow 'recurring' as source in ledger_entries
        if (config('database.default') === 'pgsql' || config('database.default') === 'pgsql_admin') {
            DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_source_check');
            DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_source_check CHECK (source IN ('manual', 'request', 'recurring'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'pgsql' || config('database.default') === 'pgsql_admin') {
            DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_source_check');
            DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_source_check CHECK (source IN ('manual', 'request'))");
        }

        Schema::table('recurring_rules', function (Blueprint $table) {
            $table->dropColumn(['items', 'direction']);
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['items', 'quantity_got']);
        });

        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropColumn(['monthly_budget_minor', 'weekly_budget_minor']);
        });
    }
};
