<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('requests')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider')->nullable();
            $table->string('provider_call_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->enum('status', [
                'queued', 'ringing', 'answered', 'no_answer', 'busy', 'failed', 'skipped', 'completed',
            ])->default('queued');
            $table->string('reason')->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_call_logs');
    }
};
