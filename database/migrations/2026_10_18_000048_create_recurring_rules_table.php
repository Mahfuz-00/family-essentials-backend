<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A recurring rule is a *recipe*, not a task: the daily command spawns a
     * fresh request from it each cycle. Nothing ever reopens a past request, so
     * the board, the history and the audit ledger stay honest.
     *
     * `next_run_on` is the whole state machine. It is advanced to the next
     * occurrence strictly after today, which means a missed cycle is skipped
     * rather than back-filled.
     */
    public function up(): void
    {
        Schema::create('recurring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('title');
            $table->string('quantity', 64)->nullable();
            $table->text('notes')->nullable();
            $table->string('category')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            $table->enum('cadence', ['daily', 'weekly', 'monthly']);
            // 0 = Sunday, matching Carbon's dayOfWeek. Required for weekly.
            $table->unsignedSmallInteger('weekday')->nullable();
            // 1-31. Required for monthly, and clamped to the month's length when
            // the month is shorter (the 31st in February runs on the 28th/29th).
            $table->unsignedSmallInteger('day_of_month')->nullable();

            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->foreignId('location_trigger_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->enum('location_trigger_direction', ['enter', 'exit'])->nullable();

            // Money is integer minor units, never a float.
            $table->bigInteger('amount_minor')->nullable();
            // Deliberately no foreign key here: `ledgers` is created by the
            // ledger migration that follows this one, and a constraint cannot
            // reference a table that does not exist yet. The constraint is added
            // there, together with the same column on `requests`.
            $table->unsignedBigInteger('ledger_id')->nullable();

            $table->date('next_run_on');
            $table->date('last_run_on')->nullable();
            $table->boolean('active')->default(true);

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // The command's only query.
            $table->index(['tenant_id', 'active', 'next_run_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_rules');
    }
};
