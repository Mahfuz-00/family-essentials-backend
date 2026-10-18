<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every arrival decision: what was sent, and why nothing was.
     *
     * The same shape of thing as `voice_call_logs` — a per-source log of decisions
     * rather than the generic delivery feed. A refusal is recorded here with its
     * reason, so "why was I not told?" always has an answer.
     *
     * `geofence_event_id` gives each decision its dwell identity: one decision per
     * recorded entry, which is what makes the unique key below meaningful.
     */
    public function up(): void
    {
        Schema::create('location_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('geofence_event_id')->nullable()
                ->constrained('geofence_events')->nullOnDelete();

            $table->enum('status', ['queued', 'sent', 'skipped', 'failed'])->default('queued');
            $table->string('reason')->nullable();
            // The location kind at decision time, so history stays readable even
            // if the place's category is changed later.
            $table->string('kind', 16);
            $table->unsignedTinyInteger('task_count')->default(0);
            $table->jsonb('task_ids')->nullable();
            $table->string('title')->nullable();
            $table->string('body')->nullable();
            $table->foreignId('notification_log_id')->nullable()
                ->constrained('notification_logs')->nullOnDelete();

            // What actually prevents a duplicate notification.
            $table->string('idempotency_key')->unique();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'place_id', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_notifications');
    }
};
