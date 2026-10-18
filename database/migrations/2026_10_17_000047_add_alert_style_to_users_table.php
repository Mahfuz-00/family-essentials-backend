<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How the member wants to be alerted. Chosen per member, because it is about
     * their phone and their tolerance for interruption.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('alert_style', ['silent', 'vibrate', 'sound', 'voice'])
                ->default('sound')
                ->after('location_notifications_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('alert_style');
        });
    }
};
