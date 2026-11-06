<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformSetting;
use App\Models\RefundRequest;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PlatformSettingsAndOperationsTest extends TestCase
{
    use CreatesTenants;

    public function test_platform_admin_can_read_and_update_payment_gateway_settings(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/payment')
            ->assertOk()
            ->assertJsonPath('data.active_gateway', 'stripe')
            ->assertJsonPath('data.mode', 'sandbox');

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment', [
                'active_gateway' => 'razorpay',
                'mode' => 'live',
                'currency' => 'EUR',
                'gateways' => [
                    'razorpay' => ['enabled' => true, 'key_id' => 'rzp_live_123', 'key_secret' => 'sec_456'],
                ],
                'auto_billing_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.active_gateway', 'razorpay')
            ->assertJsonPath('data.currency', 'EUR');

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'razorpay'])
            ->assertOk()
            ->assertJsonPath('status', 'connected');
    }

    public function test_platform_admin_can_read_and_update_email_configuration(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/settings/email')
            ->assertOk()
            ->assertJsonPath('data.mailer', 'smtp');

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/email', [
                'mailer' => 'ses',
                'from_address' => 'support@fairshare.app',
                'from_name' => 'FairShare Support',
            ])
            ->assertOk()
            ->assertJsonPath('data.mailer', 'ses')
            ->assertJsonPath('data.from_address', 'support@fairshare.app');

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/email/test', [
                'recipient' => 'test@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_platform_admin_can_manage_refund_policy_and_requests(): void
    {
        $platform = $this->createPlatformAdmin();
        $tenant = $this->createTenant('Refund Household');

        // 1. Read policy and empty refunds
        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/refunds')
            ->assertOk()
            ->assertJsonStructure(['policy', 'refunds', 'stats']);

        // 2. Update policy
        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/refunds/policy', [
                'policy_markdown' => '# Updated Refund Policy',
                'refund_window_days' => 30,
                'auto_approve_under_cents' => 1000,
                'pro_rata_enabled' => true,
                'contact_email' => 'refunds@fairshare.app',
            ])
            ->assertOk()
            ->assertJsonPath('policy.refund_window_days', 30);

        // 3. Create a refund request
        $createRes = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/refunds', [
                'tenant_id' => $tenant->id,
                'tenant_name' => 'Refund Household',
                'user_email' => 'member@example.com',
                'amount_cents' => 1900,
                'currency' => 'USD',
                'reason' => 'Duplicate accidental charge on renewal.',
            ])
            ->assertCreated();

        $refundId = $createRes->json('refund.id');

        // 4. Approve refund
        $this->withToken($this->tokenFor($platform))
            ->postJson("/api/v1/platform/refunds/{$refundId}/approve", [
                'admin_notes' => 'Verified with payment gateway transaction.',
            ])
            ->assertOk()
            ->assertJsonPath('refund.status', 'approved');
    }

    public function test_platform_admin_can_read_analytics_and_manage_health(): void
    {
        $platform = $this->createPlatformAdmin();
        $tenant = $this->createTenant('Analytics Household');

        // 1. Read Analytics
        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/analytics')
            ->assertOk()
            ->assertJsonStructure([
                'overview' => ['mrr_dollars', 'arr_dollars', 'total_households'],
                'by_status',
                'by_tier',
                'errand_metrics',
                'timeseries',
            ]);

        // 2. Health & Maintenance
        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/health/maintenance')
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/health/maintenance', [
                'enabled' => true,
                'message' => 'Performing database maintenance.',
            ])
            ->assertOk()
            ->assertJsonPath('data.enabled', true);

        // 3. Cache flush
        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/health/cache/clear')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
