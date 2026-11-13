<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('essentials', function (Blueprint $table) {
            $table->unsignedInteger('unit_price_minor')->default(0)->after('stock_level');
            $table->unsignedInteger('last_spent_minor')->default(0)->after('unit_price_minor');
            $table->unsignedBigInteger('total_spent_minor')->default(0)->after('last_spent_minor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('essentials', function (Blueprint $table) {
            $table->dropColumn(['unit_price_minor', 'last_spent_minor', 'total_spent_minor']);
        });
    }
};
