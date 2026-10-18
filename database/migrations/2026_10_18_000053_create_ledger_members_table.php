<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who may see a ledger, and how much of it.
     *
     *   owner   - manages membership and sees everything
     *   manager - sees and creates entries
     *   summary - sees aggregates only, never a single entry
     *
     * The distinction between "which ledgers" (here) and "how much detail" (the
     * API) is deliberate: this table decides visibility, the policy decides
     * depth.
     */
    public function up(): void
    {
        Schema::create('ledger_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('ledger_id')->constrained('ledgers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['owner', 'manager', 'summary'])->default('summary');
            $table->timestamps();

            $table->unique(['ledger_id', 'user_id']);
            // The person-side lookup every ledger policy performs through its
            // EXISTS subquery.
            $table->index(['user_id', 'ledger_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_members');
    }
};
