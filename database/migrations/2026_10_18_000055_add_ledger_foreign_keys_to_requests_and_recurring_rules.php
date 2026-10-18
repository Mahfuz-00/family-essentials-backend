<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two columns that pointed at `ledgers` before it existed: a request can
     * be raised against a ledger (`amount_minor` + `ledger_id`), and a recurring
     * rule can carry the same pair into the request it spawns.
     *
     * The constraints live here rather than in the create migrations because a
     * foreign key cannot reference a table that has not been created yet.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreign('ledger_id')->references('id')->on('ledgers')->nullOnDelete();
        });

        Schema::table('recurring_rules', function (Blueprint $table) {
            $table->foreign('ledger_id')->references('id')->on('ledgers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['ledger_id']);
        });

        Schema::table('recurring_rules', function (Blueprint $table) {
            $table->dropForeign(['ledger_id']);
        });
    }
};
