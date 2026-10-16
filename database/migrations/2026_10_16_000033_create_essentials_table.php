<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recurring household essentials ("buffer / pantry").
     *
     * Each row tracks one staple the household restocks on a cadence. An item is
     * due when it falls to or below its safety-stock threshold, or when its
     * cadence has elapsed since the last restock. This is what the mobile
     * "Pantry pulse" and safety-stock alert screens read from.
     */
    public function up(): void
    {
        Schema::create('essentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('default_store')->nullable();
            $table->string('pack_size', 64)->nullable();
            $table->unsignedSmallInteger('cadence_days')->default(14);
            $table->unsignedSmallInteger('threshold')->default(1);
            $table->unsignedSmallInteger('stock_level')->default(0);
            $table->timestamp('last_restocked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('essentials');
    }
};
