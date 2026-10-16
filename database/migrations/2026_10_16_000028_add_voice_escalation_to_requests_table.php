<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('voice_escalation_attempts')->default(0)->after('snooze_until');
            $table->timestamp('last_voice_attempt_at')->nullable()->after('voice_escalation_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['voice_escalation_attempts', 'last_voice_attempt_at']);
        });
    }
};
