<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The backup registry: what was taken, how big it was, its checksum, and
     * whether that checksum still matches the file on disk.
     *
     * Platform-level like the ops tables — it describes the system, not a
     * household. A backup that cannot be verified is not a backup, which is why
     * the checksum and the verification result are first-class columns rather
     * than operator notes.
     */
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->unique();
            $table->string('disk')->default('local');
            $table->string('driver')->default('pg_dump');
            $table->enum('type', ['manual', 'scheduled', 'pre_restore'])->default('manual');
            $table->enum('status', ['running', 'completed', 'failed', 'skipped'])->default('running');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verify_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
