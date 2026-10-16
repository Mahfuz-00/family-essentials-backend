<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant-authored feedback, bug reports and feature requests.
     *
     * A household writes these; the platform admin reads them across households
     * and triages them. Tenant-scoped like every other household table, so one
     * household can never read another's report.
     */
    public function up(): void
    {
        Schema::create('feedback_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['bug', 'feedback', 'feature_request'])->default('bug');
            // The reporter's own sense of urgency; the platform can change it.
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');
            $table->enum('status', ['new', 'triaged', 'in_progress', 'resolved', 'wont_fix'])
                ->default('new');

            $table->string('subject');
            $table->text('body');
            // Whatever the client could tell us: platform, app version, the screen
            // it happened on. Free-form on purpose — it aids triage, it is not data.
            $table->jsonb('context')->nullable();

            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_reports');
    }
};
