<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `families` table was a Phase 1 placeholder; `tenants` replaces it.
     */
    public function up(): void
    {
        Schema::dropIfExists('families');
    }

    public function down(): void
    {
        // Intentionally not recreated — superseded by `tenants`.
    }
};
