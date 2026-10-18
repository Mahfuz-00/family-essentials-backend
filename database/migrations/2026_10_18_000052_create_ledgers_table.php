<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ledger is the money axis of the household — and the first thing in this
     * schema that is scoped *per person* rather than per household: a member
     * sees the ledgers they belong to, not every ledger in the house.
     *
     * The currency is fixed at creation from config, so a ledger has one unit
     * for its whole life (see config/ledger.php).
     */
    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->char('currency', 3)->default((string) config('ledger.currency'));
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledgers');
    }
};
