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

        // 4b. Before approval, pending cash invoices cannot be digitally downloaded by tenant
        $this->withToken($this->tokenFor($user))
            ->get('/api/v1/billing/invoices/'.$cashInvoiceId.'/download')
            ->assertStatus(400);

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

        // Once cash payment is accepted by platform admin, tenant CAN download the cash invoice from dashboard
        $tenantDownloadRes = $this->withToken($this->tokenFor($user))
            ->get('/api/v1/billing/invoices/'.$cashInvoiceId.'/download')
            ->assertOk();
        $this->assertStringContainsString('Paid in Cash (In-Person)', $tenantDownloadRes->getContent());

        // Platform Admin also CAN download the physical handover copy
        $adminDownloadRes = $this->withToken($this->tokenFor($platform))
            ->get('/api/v1/platform/billing/invoices/'.$cashInvoiceId.'/download')
            ->assertOk();
        $this->assertStringContainsString('Physical In-Person Cash Settlement Handover Receipt', $adminDownloadRes->getContent());

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

    public function test_saas_refund_policy_rules_and_eligibility_engine(): void
    {
        $platform = $this->createPlatformAdmin();
        $tenant = $this->createTenant('Refund Policy Household');

        // 1. Create a monthly invoice issued 5 days ago (within 7-day window -> 100% refund)
        $recentMonthlyInvoice = \App\Models\Invoice::create([
            'tenant_id' => $tenant->id,
            'invoice_number' => \App\Models\Invoice::generateNumber(),
            'amount_cents' => 1900,
            'currency' => 'USD',
            'status' => 'paid',
            'tier' => 'family',
            'billing_period' => 'monthly',
            'paid_at' => now()->subDays(5),
            'payment_method' => 'credit_card',
            'last4' => '4242',
        ]);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/refunds/eligibility', [
                'invoice_id' => $recentMonthlyInvoice->id,
            ])
            ->assertOk()
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('refund_percent', 100)
            ->assertJsonPath('recommended_refund_cents', 1900);

        // 2. Monthly invoice issued 10 days ago (past 7-day window -> ineligible)
        $expiredMonthlyInvoice = \App\Models\Invoice::create([
            'tenant_id' => $tenant->id,
            'invoice_number' => \App\Models\Invoice::generateNumber(),
            'amount_cents' => 1900,
            'currency' => 'USD',
            'status' => 'paid',
            'tier' => 'family',
            'billing_period' => 'monthly',
            'paid_at' => now()->subDays(10),
            'payment_method' => 'credit_card',
            'last4' => '4242',
        ]);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/refunds/eligibility', [
                'invoice_id' => $expiredMonthlyInvoice->id,
            ])
            ->assertOk()
            ->assertJsonPath('eligible', false)
            ->assertJsonPath('refund_percent', 0)
            ->assertJsonPath('recommended_refund_cents', 0);

        // 3. Annual invoice issued 20 days ago (within 30-day window -> 80% refund)
        $recentAnnualInvoice = \App\Models\Invoice::create([
            'tenant_id' => $tenant->id,
            'invoice_number' => \App\Models\Invoice::generateNumber(),
            'amount_cents' => 19000,
            'currency' => 'USD',
            'status' => 'paid',
            'tier' => 'family',
            'billing_period' => 'annual',
            'paid_at' => now()->subDays(20),
            'payment_method' => 'credit_card',
            'last4' => '4242',
        ]);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/refunds/eligibility', [
                'invoice_id' => $recentAnnualInvoice->id,
            ])
            ->assertOk()
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('refund_percent', 80)
            ->assertJsonPath('recommended_refund_cents', 15200);

        // 4. Annual invoice issued 45 days ago (past 30-day window -> ineligible)
        $expiredAnnualInvoice = \App\Models\Invoice::create([
            'tenant_id' => $tenant->id,
            'invoice_number' => \App\Models\Invoice::generateNumber(),
            'amount_cents' => 19000,
            'currency' => 'USD',
            'status' => 'paid',
            'tier' => 'family',
            'billing_period' => 'annual',
            'paid_at' => now()->subDays(45),
            'payment_method' => 'credit_card',
            'last4' => '4242',
        ]);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/refunds/eligibility', [
                'invoice_id' => $expiredAnnualInvoice->id,
            ])
            ->assertOk()
            ->assertJsonPath('eligible', false)
            ->assertJsonPath('refund_percent', 0);
    }

    public function test_saas_plan_change_lock_and_mid_cycle_adjustment_rules(): void
    {
        $tenant = $this->createTenant('Plan Change Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        // Case A: Day 1 of monthly plan (< 3 days) -> Locked early grace period
        $this->withBypass(function () use ($tenant) {
            $tenant->subscription_tier = 'plus';
            $tenant->save();

            \App\Models\Invoice::create([
                'tenant_id' => $tenant->id,
                'invoice_number' => \App\Models\Invoice::generateNumber(),
                'amount_cents' => 900,
                'currency' => 'USD',
                'status' => 'paid',
                'tier' => 'plus',
                'billing_period' => 'monthly',
                'period_start' => now()->subDay(),
                'paid_at' => now()->subDay(),
                'payment_method' => 'credit_card',
                'last4' => '4242',
            ]);
        });

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/change-plan-preview', [
                'tier' => 'family',
                'billing_period' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonPath('eligible', false)
            ->assertJsonPath('policy_code', 'monthly_early_lock');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/upgrade', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'payment_method' => 'credit_card',
            ])
            ->assertStatus(422)
            ->assertJsonPath('policy_code', 'monthly_early_lock');

        // Case B: Day 10 of monthly plan (Between 3 and 20 days) -> 50% adjustment discount
        $this->withBypass(function () use ($tenant) {
            \App\Models\Invoice::where('tenant_id', $tenant->id)->update([
                'period_start' => now()->subDays(10),
                'paid_at' => now()->subDays(10),
            ]);
        });

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/change-plan-preview', [
                'tier' => 'family',
                'billing_period' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('adjustment_percent', 50)
            ->assertJsonPath('final_amount_cents', 950); // 50% of 1900

        $upgradeRes = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/upgrade', [
                'tier' => 'family',
                'billing_period' => 'monthly',
                'payment_method' => 'credit_card',
            ])
            ->assertCreated()
            ->assertJsonPath('invoice.amount_cents', 950);

        // Case C: Day 25 of monthly plan (> 20 days) -> Locked late cycle freeze
        $this->withBypass(function () use ($tenant) {
            \App\Models\Invoice::where('tenant_id', $tenant->id)->update([
                'period_start' => now()->subDays(25),
                'paid_at' => now()->subDays(25),
            ]);
        });

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/change-plan-preview', [
                'tier' => 'enterprise',
                'billing_period' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonPath('eligible', false)
            ->assertJsonPath('policy_code', 'monthly_late_freeze');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/upgrade', [
                'tier' => 'enterprise',
                'billing_period' => 'monthly',
                'payment_method' => 'credit_card',
            ])
            ->assertStatus(422)
            ->assertJsonPath('policy_code', 'monthly_late_freeze');

        // Case D: Annual plan > 90 days (3 months) -> Locked after 3 months
        $this->withBypass(function () use ($tenant) {
            \App\Models\Invoice::where('tenant_id', $tenant->id)->update([
                'billing_period' => 'annual',
                'period_start' => now()->subDays(100),
                'paid_at' => now()->subDays(100),
            ]);
        });

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/change-plan-preview', [
                'tier' => 'enterprise',
                'billing_period' => 'annual',
            ])
            ->assertOk()
            ->assertJsonPath('eligible', false)
            ->assertJsonPath('policy_code', 'annual_late_freeze');

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/billing/upgrade', [
                'tier' => 'enterprise',
                'billing_period' => 'annual',
                'payment_method' => 'credit_card',
            ])
            ->assertStatus(422)
            ->assertJsonPath('policy_code', 'annual_late_freeze');
    }
}
