<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->enum('type', ['due', 'snooze'])->default('due');
            $table->timestamp('fire_at');
            $table->enum('status', ['pending', 'sent', 'cancelled', 'failed'])->default('pending');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'fire_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
