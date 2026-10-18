<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opt-in per member, defaulting to off.
     *
     * Arrival notifications are a new, unasked-for interruption, so the safe
     * default for every existing row is "no" — a member turns them on.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('location_notifications_opt_in')
                ->default(false)
                ->after('voice_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('location_notifications_opt_in');
        });
    }
};
