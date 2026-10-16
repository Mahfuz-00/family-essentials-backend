<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Operational telemetry for the landlord console.
     *
     * Both tables are platform-level, like `audit_events`: they describe the
     * system rather than any one household, so they carry no tenant_id and no
     * row-level security, and are only reachable through `platform.admin`.
     */
    public function up(): void
    {
        Schema::create('health_samples', function (Blueprint $table) {
            $table->id();
            $table->timestamp('checked_at')->index();
            $table->enum('status', ['ok', 'warn', 'fail'])->index();
            $table->jsonb('checks')->nullable();
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('severity', ['minor', 'major', 'critical'])->default('minor');
            $table->enum('status', ['investigating', 'identified', 'monitoring', 'resolved'])
                ->default('investigating');
            $table->text('body')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('health_samples');
    }
};
