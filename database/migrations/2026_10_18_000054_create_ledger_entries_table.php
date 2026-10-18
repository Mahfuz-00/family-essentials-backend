<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One movement of money.
     *
     * `amount_minor` is always an integer count of minor units — a float never
     * enters this table, because 0.1 + 0.2 is not 0.3 and a household budget
     * that does not add up is worse than no budget at all.
     *
     * `request_id` is unique: completing a request that carries a ledger records
     * exactly one expense, however many times the completion is retried. It is
     * `nullOnDelete` rather than `cascadeOnDelete` — deleting the task does not
     * un-spend the money.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('ledger_id')->constrained('ledgers')->cascadeOnDelete();

            $table->bigInteger('amount_minor');
            $table->enum('direction', ['expense', 'income']);
            $table->string('category')->nullable();
            $table->string('description', 500)->nullable();
            $table->date('occurred_on');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('source', ['manual', 'request'])->default('manual');
            $table->foreignId('request_id')->nullable()->constrained('requests')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // The idempotency guarantee for the completion bridge.
            $table->unique('request_id');

            // The ledger statement, and the summary's period scan.
            $table->index(['tenant_id', 'ledger_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
