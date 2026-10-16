<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-wide security ledger.
     *
     * Deliberately NOT tenant-scoped and NOT covered by row-level security:
     * these rows describe cross-tenant activity (provisioning, suspensions,
     * denied platform access) and are only ever exposed through the
     * `platform.admin` middleware.
     */
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('trace_id')->unique();
            $table->timestamp('occurred_at');
            $table->enum('level', ['info', 'warning', 'critical'])->default('info');
            $table->string('action', 96);
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_email')->nullable();
            $table->string('actor_role', 32)->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamps();

            $table->index(['occurred_at', 'level']);
            $table->index('action');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
