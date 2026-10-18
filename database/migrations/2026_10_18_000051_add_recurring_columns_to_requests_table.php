<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a spawned request remembers about the cycle that produced it.
     *
     * The unique index is the idempotency guarantee: a retried (or duplicated)
     * command run cannot produce two requests for the same rule and cycle date.
     * PostgreSQL treats NULLs as distinct, so ordinary hand-raised requests are
     * unaffected.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('recurring_rule_id')->nullable()
                ->constrained('recurring_rules')->nullOnDelete();
            $table->date('recurring_for')->nullable();

            // Money, in integer minor units. `ledger_id` gets its foreign key in
            // the ledger migration.
            $table->bigInteger('amount_minor')->nullable();
            $table->unsignedBigInteger('ledger_id')->nullable();

            $table->unique(['recurring_rule_id', 'recurring_for']);
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropUnique(['recurring_rule_id', 'recurring_for']);
            $table->dropConstrainedForeignId('recurring_rule_id');
            $table->dropColumn(['recurring_for', 'amount_minor', 'ledger_id']);
        });
    }
};
