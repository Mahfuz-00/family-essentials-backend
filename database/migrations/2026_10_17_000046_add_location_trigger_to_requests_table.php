<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The reminder rule belongs to the task: *which* place should cue it, and
     * whether on entering or leaving.
     *
     * `requests.place_id` keeps its existing meaning — the shop to buy at — which
     * is a different fact from "remind me when I leave the office".
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('location_trigger_place_id')->nullable()
                ->constrained('places')->nullOnDelete();
            $table->enum('location_trigger_direction', ['enter', 'exit'])->nullable();

            $table->index(['tenant_id', 'location_trigger_place_id'], 'requests_trigger_place_index');
        });

        // Existing tasks that carry a shop used to imply "tell me when I arrive
        // there"; preserve that rather than silently switching notifications off.
        //
        // The write needs the RLS bypass: `requests` is FORCE ROW LEVEL SECURITY
        // and migrations run as the table owner, which the policy applies to.
        DB::statement("select set_config('app.bypass_rls', 'on', false)");

        try {
            DB::table('requests')
                ->whereNotNull('place_id')
                ->update([
                    'location_trigger_place_id' => DB::raw('place_id'),
                    'location_trigger_direction' => 'enter',
                ]);
        } finally {
            DB::statement("select set_config('app.bypass_rls', 'off', false)");
        }
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex('requests_trigger_place_index');
            $table->dropConstrainedForeignId('location_trigger_place_id');
            $table->dropColumn('location_trigger_direction');
        });
    }
};
