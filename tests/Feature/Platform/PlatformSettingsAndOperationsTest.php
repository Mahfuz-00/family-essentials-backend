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
            ->assertJsonPath('data.mode', 'sandbox')
            ->assertJsonPath('data.gateways.cash.enabled', true);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment', [
                'active_gateway' => 'cash',
                'mode' => 'live',
                'currency' => 'EUR',
                'gateways' => [
                    'cash' => [
                        'enabled' => true,
                        'instructions' => 'Hand cash directly to representative.',
                        'auto_mark_paid' => true,
                    ],
                ],
                'auto_billing_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.active_gateway', 'cash')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.gateways.cash.instructions', 'Hand cash directly to representative.');

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment/test', ['gateway' => 'cash'])
            ->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('gateway', 'cash');
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

    public function test_tenant_can_view_payment_methods_and_upgrade_with_cash_and_rejects_when_cash_disabled(): void
    {
        $platform = $this->createPlatformAdmin();
        $tenant = $this->createTenant('Cash Billing Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        // 1. Enable cash payment with in-person approval required (auto_mark_paid = false)
        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment', [
                'active_gateway' => 'stripe',
                'mode' => 'sandbox',
                'currency' => 'USD',
                'gateways' => [
                    'cash' => [
                        'enabled' => true,
                        'instructions' => 'Settlement in person at reception.',
                        'auto_mark_paid' => false,
                    ],
                ],
                'auto_billing_enabled' => true,
            ])
            ->assertOk();

        // 2. Tenant reads payment methods
        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/billing/payment-methods')
            ->assertOk()
            ->assertJsonPath('data.cash.enabled', true)
            ->assertJsonPath('data.cash.instructions', 'Settlement in person at reception.');

        // 3. Tenant submits upgrade with cash payment (enters pending state)
        $upgradeRes = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/upgrade', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'payment_method' => 'cash',
            ])
            ->assertCreated()
            ->assertJsonPath('invoice.payment_method', 'cash')
            ->assertJsonPath('invoice.last4', 'CASH')
            ->assertJsonPath('invoice.status', 'pending');

        $cashInvoiceId = $upgradeRes->json('invoice.id');

        // Household is not yet upgraded until admin approval
        $freshTenant = $this->withBypass(fn () => $tenant->fresh());
        $this->assertSame('pilot', $freshTenant->subscription_tier);

        // 4. Platform admin receives notification of pending cash settlement
        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/notifications')
            ->assertOk()
            ->assertJsonPath('pending_cash_count', 1)
            ->assertJsonPath('cash_settlements.0.id', $cashInvoiceId)
            ->assertJsonPath('cash_settlements.0.tenant.name', 'Cash Billing Household');

        // 5. Platform admin checks pending queue and approves cash payment
        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/billing/pending-cash')
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/billing/invoices/'.$cashInvoiceId.'/approve-cash')
            ->assertOk()
            ->assertJsonPath('invoice.status', 'paid');

        // Household tier is now upgraded to family upon admin cash approval
        $approvedTenant = $this->withBypass(fn () => $tenant->fresh());
        $this->assertSame('family', $approvedTenant->subscription_tier);

        // Cash invoices cannot be digitally downloaded because physical paper invoice is issued in person
        $this->withToken($this->tokenFor($user))
            ->get('/api/v1/billing/invoices/'.$cashInvoiceId.'/download')
            ->assertStatus(400);

        // 6. Platform admin disables cash payment
        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/settings/payment', [
                'active_gateway' => 'stripe',
                'mode' => 'sandbox',
                'currency' => 'USD',
                'gateways' => [
                    'cash' => [
                        'enabled' => false,
                        'instructions' => 'Disabled',
                        'auto_mark_paid' => false,
                    ],
                ],
                'auto_billing_enabled' => true,
            ])
            ->assertOk();

        // 5. Tenant reads payment methods and cash is disabled
        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/billing/payment-methods')
            ->assertOk()
            ->assertJsonPath('data.cash.enabled', false);

        // 6. Tenant attempt to upgrade with cash is rejected
        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/upgrade', [
                'tier' => 'enterprise',
                'billing_period' => 'annual',
                'payment_method' => 'cash',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cash payment is currently disabled by platform administration.');
    }
}
