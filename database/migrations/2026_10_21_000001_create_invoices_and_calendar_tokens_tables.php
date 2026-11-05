<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Invoices & Billing Transactions
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('invoice_number', 64)->unique();
            $table->unsignedBigInteger('amount_cents'); // Minor unit integer e.g. 1900 = $19.00
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('paid'); // 'paid', 'pending', 'failed', 'refunded'
            $table->string('tier', 32)->default('plus');
            $table->string('billing_period', 32)->default('monthly'); // 'monthly', 'annual'
            $table->timestampTz('period_start')->nullable();
            $table->timestampTz('period_end')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->string('payment_method', 64)->default('credit_card'); // 'credit_card', 'paypal', 'apple_pay'
            $table->string('last4', 4)->nullable();
            $table->jsonb('line_items')->nullable();
            $table->timestampsTz();
        });

        // 2. Calendar Feed Tokens for iCal / Google / Apple Calendar sync
        Schema::create('calendar_feed_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('scope', 32)->default('household'); // 'household' or 'member'
            $table->timestampTz('last_accessed_at')->nullable();
            $table->timestampsTz();
        });

        // Ensure Postgres runtime user 'family_app' has full table & sequence permissions
        if (config('database.default') === 'pgsql' || config('database.default') === 'pgsql_admin') {
            DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE invoices, calendar_feed_tokens TO family_app');
            DB::statement('GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO family_app');
        }

        // Seed sample invoices for existing tenants
        $tenant = DB::table('tenants')->first();
        if ($tenant) {
            DB::table('invoices')->insert([
                [
                    'tenant_id' => $tenant->id,
                    'invoice_number' => 'INV-2026-0001',
                    'amount_cents' => 1900,
                    'currency' => 'USD',
                    'status' => 'paid',
                    'tier' => 'family',
                    'billing_period' => 'monthly',
                    'period_start' => now()->subMonth(),
                    'period_end' => now(),
                    'paid_at' => now()->subMonth(),
                    'payment_method' => 'credit_card',
                    'last4' => '4242',
                    'line_items' => json_encode([
                        ['description' => 'FairShare Family Plan (Monthly)', 'quantity' => 1, 'amount_cents' => 1900],
                    ]),
                    'created_at' => now()->subMonth(),
                    'updated_at' => now()->subMonth(),
                ],
                [
                    'tenant_id' => $tenant->id,
                    'invoice_number' => 'INV-2026-0002',
                    'amount_cents' => 1900,
                    'currency' => 'USD',
                    'status' => 'paid',
                    'tier' => 'family',
                    'billing_period' => 'monthly',
                    'period_start' => now(),
                    'period_end' => now()->addMonth(),
                    'paid_at' => now(),
                    'payment_method' => 'credit_card',
                    'last4' => '4242',
                    'line_items' => json_encode([
                        ['description' => 'FairShare Family Plan (Monthly)', 'quantity' => 1, 'amount_cents' => 1900],
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_feed_tokens');
        Schema::dropIfExists('invoices');
    }
};
