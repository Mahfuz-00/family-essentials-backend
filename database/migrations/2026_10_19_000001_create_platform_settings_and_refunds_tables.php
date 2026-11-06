<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->jsonb('value');
            $table->timestamps();
        });

        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('tenant_name');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_email')->nullable();
            $table->integer('amount_cents')->default(0);
            $table->string('currency', 8)->default('USD');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('admin_notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // Grant permissions to runtime PostgreSQL app role if applicable
        if ($this->roleExists('family_app')) {
            foreach (['platform_settings', 'refund_requests'] as $table) {
                DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$table} TO family_app");
                if ($this->sequenceExists("{$table}_id_seq")) {
                    DB::statement("GRANT USAGE, SELECT ON SEQUENCE {$table}_id_seq TO family_app");
                }
            }
        }

        // Seed initial platform configurations
        DB::table('platform_settings')->insert([
            [
                'key' => 'payment_gateway',
                'value' => json_encode([
                    'active_gateway' => 'stripe',
                    'mode' => 'sandbox',
                    'currency' => 'USD',
                    'gateways' => [
                        'stripe' => [
                            'enabled' => true,
                            'publishable_key' => 'pk_test_fairshare_sandbox_mock51928374',
                            'secret_key' => 'sk_test_••••••••••••••••••••••••••••••••',
                            'webhook_secret' => 'whsec_••••••••••••••••••••••••••••',
                        ],
                        'razorpay' => [
                            'enabled' => false,
                            'key_id' => 'rzp_test_fairshare',
                            'key_secret' => '••••••••••••••••',
                        ],
                        'sslcommerz' => [
                            'enabled' => false,
                            'store_id' => 'fairshare_sandbox',
                            'store_passwd' => '••••••••••••••••',
                        ],
                        'paypal' => [
                            'enabled' => false,
                            'client_id' => 'sb-fairshare-mock-id',
                            'client_secret' => '••••••••••••••••',
                        ],
                    ],
                    'webhook_url' => '/api/v1/webhooks/payment',
                    'auto_billing_enabled' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'email_config',
                'value' => json_encode([
                    'mailer' => 'smtp',
                    'host' => 'smtp.mailgun.org',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => 'postmaster@mg.fairshare.app',
                    'password' => '••••••••••••••••',
                    'from_address' => 'notifications@fairshare.app',
                    'from_name' => 'FairShare Household Sync',
                    'templates' => [
                        ['id' => 'welcome', 'name' => 'Welcome & Household Onboarding', 'enabled' => true],
                        ['id' => 'task_assigned', 'name' => 'Task & Errand Shared Assignment', 'enabled' => true],
                        ['id' => 'voice_escalation', 'name' => 'Voice Priority Escalation Call', 'enabled' => true],
                        ['id' => 'weekly_digest', 'name' => 'Weekly Shared Responsibilities Digest', 'enabled' => true],
                        ['id' => 'password_reset', 'name' => 'Password & Security Verification', 'enabled' => true],
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'refund_policy',
                'value' => json_encode([
                    'policy_markdown' => "# FairShare Household Operations — Refund Policy\n\nWe want every household to thrive with FairShare. If you are not completely satisfied with your subscription, we offer an open and fair refund policy.\n\n### 14-Day Money-Back Guarantee\nAll new paid subscriptions (Plus, Family, and Enterprise) come with an unconditional 14-day money-back guarantee from the date of the first charge.\n\n### Pro-Rata Annual Refunds\nFor annual plans cancelled after the initial 14-day guarantee period, we credit pro-rated refunds for all remaining unused full calendar months.\n\n### Processing Timeline\nRefund requests submitted through billing or platform administration are reviewed within 2 business days. Approved refunds are credited directly back to the original payment method.",
                    'refund_window_days' => 14,
                    'auto_approve_under_cents' => 0,
                    'pro_rata_enabled' => true,
                    'contact_email' => 'billing@fairshare.app',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'maintenance_mode',
                'value' => json_encode([
                    'enabled' => false,
                    'message' => 'FairShare is currently undergoing scheduled platform upgrades. We will be back online shortly.',
                    'started_at' => null,
                    'allowed_ips' => [],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->roleExists('family_app')) {
            foreach (['refund_requests', 'platform_settings'] as $table) {
                if ($this->tableExists($table)) {
                    DB::statement("REVOKE ALL ON {$table} FROM family_app");
                }
            }
        }

        Schema::dropIfExists('refund_requests');
        Schema::dropIfExists('platform_settings');
    }

    private function roleExists(string $role): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role]) !== null;
    }

    private function tableExists(string $table): bool
    {
        return DB::selectOne(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
            [$table]
        ) !== null;
    }

    private function sequenceExists(string $sequence): bool
    {
        return DB::selectOne(
            "SELECT 1 FROM information_schema.sequences WHERE sequence_schema = 'public' AND sequence_name = ?",
            [$sequence]
        ) !== null;
    }
};
