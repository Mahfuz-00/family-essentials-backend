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
        // 1. Marketing Promotions & Coupons
        Schema::create('marketing_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('description', 255)->nullable();
            $table->string('discount_type', 32)->default('percent'); // 'percent' or 'fixed_amount'
            $table->unsignedInteger('discount_value'); // e.g. 20 for 20%, or 1000 for $10.00
            $table->string('currency', 3)->default('USD');
            $table->string('duration', 32)->default('once'); // 'once', 'repeating', 'forever'
            $table->unsignedInteger('duration_in_months')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('times_redeemed')->default(0);
            $table->jsonb('applicable_tiers')->nullable(); // e.g. ["starter", "plus", "family"]
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        // 2. Marketing Announcements & Broadcast Banners
        Schema::create('marketing_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('message');
            $table->string('cta_text', 128)->nullable();
            $table->string('cta_url', 255)->nullable();
            $table->string('tone', 32)->default('primary'); // 'primary', 'success', 'warning', 'info'
            $table->string('target_audience', 64)->default('all'); // 'all', 'trial_only', 'free_only', 'paying_only'
            $table->boolean('is_active')->default(true);
            $table->boolean('dismissable')->default(true);
            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
        });

        // 3. Marketing Leads & Waitlist
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->unique();
            $table->string('name', 255)->nullable();
            $table->string('source', 64)->default('website'); // 'website', 'waitlist', 'referral', 'campaign'
            $table->string('campaign', 128)->nullable();
            $table->string('status', 32)->default('new'); // 'new', 'contacted', 'converted', 'unsubscribed'
            $table->text('notes')->nullable();
            $table->foreignId('converted_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->timestampsTz();
        });

        // Ensure Postgres runtime user 'family_app' has full table & sequence permissions
        if (config('database.default') === 'pgsql' || config('database.default') === 'pgsql_admin') {
            DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE marketing_promotions, marketing_announcements, marketing_leads TO family_app');
            DB::statement('GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO family_app');
        }

        // Seed initial default promotions and announcement
        DB::table('marketing_promotions')->insert([
            [
                'code' => 'WELCOME20',
                'description' => '20% off for the first month for new family signups',
                'discount_type' => 'percent',
                'discount_value' => 20,
                'currency' => 'USD',
                'duration' => 'once',
                'duration_in_months' => 1,
                'max_redemptions' => 500,
                'times_redeemed' => 38,
                'applicable_tiers' => json_encode(['plus', 'family']),
                'starts_at' => now()->subDays(30),
                'expires_at' => now()->addMonths(3),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'FAIRSHARE10',
                'description' => '$10.00 credit towards annual Family+ subscription',
                'discount_type' => 'fixed_amount',
                'discount_value' => 1000,
                'currency' => 'USD',
                'duration' => 'once',
                'duration_in_months' => null,
                'max_redemptions' => 200,
                'times_redeemed' => 14,
                'applicable_tiers' => json_encode(['family', 'enterprise']),
                'starts_at' => now()->subDays(10),
                'expires_at' => now()->addMonths(6),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('marketing_announcements')->insert([
            [
                'title' => 'Spring Family Savings: 20% Off Family+ Tier',
                'message' => 'Supercharge your household coordination with unlimited members, location alerts, and voice escalation.',
                'cta_text' => 'Upgrade Household',
                'cta_url' => '/settings/billing',
                'tone' => 'primary',
                'target_audience' => 'all',
                'is_active' => true,
                'dismissable' => true,
                'impressions_count' => 1420,
                'clicks_count' => 84,
                'starts_at' => now()->subDays(7),
                'expires_at' => now()->addDays(23),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('marketing_leads')->insert([
            [
                'email' => 'sarah.miller@example.com',
                'name' => 'Sarah Miller',
                'source' => 'website',
                'campaign' => 'spring_promo',
                'status' => 'new',
                'notes' => 'Requested info regarding 5-member household setup',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'email' => 'david.chen@example.org',
                'name' => 'David Chen',
                'source' => 'waitlist',
                'campaign' => 'organic',
                'status' => 'contacted',
                'notes' => 'Sent welcome onboarding guide and promo voucher',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(1),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
        Schema::dropIfExists('marketing_announcements');
        Schema::dropIfExists('marketing_promotions');
    }
};
