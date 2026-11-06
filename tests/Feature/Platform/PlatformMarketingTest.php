<?php

namespace Tests\Feature\Platform;

use App\Models\MarketingAnnouncement;
use App\Models\MarketingLead;
use App\Models\MarketingPromotion;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PlatformMarketingTest extends TestCase
{
    use CreatesTenants;

    public function test_guests_cannot_access_platform_marketing(): void
    {
        $this->getJson('/api/v1/platform/marketing/overview')
            ->assertUnauthorized();
    }

    public function test_regular_tenants_cannot_access_platform_marketing(): void
    {
        $tenant = $this->createTenant('Test Household');
        $user = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/platform/marketing/overview')
            ->assertForbidden();
    }

    public function test_platform_admin_can_retrieve_marketing_overview(): void
    {
        $platform = $this->createPlatformAdmin();

        MarketingPromotion::create([
            'code' => 'TEST50',
            'discount_type' => 'percent',
            'discount_value' => 50,
            'is_active' => true,
            'times_redeemed' => 12,
        ]);

        MarketingAnnouncement::create([
            'title' => 'Test Banner',
            'message' => 'Banner message',
            'is_active' => true,
            'impressions_count' => 100,
            'clicks_count' => 10,
        ]);

        MarketingLead::create([
            'email' => 'lead_test_overview@example.com',
            'name' => 'Lead One',
            'status' => 'new',
        ]);

        $response = $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/marketing/overview')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_leads',
                    'new_leads',
                    'converted_leads',
                    'active_promotions',
                    'total_redemptions',
                    'active_announcements',
                    'total_impressions',
                    'total_clicks',
                    'banner_ctr_percent',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.total_leads'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.active_promotions'));
    }

    public function test_platform_admin_can_manage_promotions(): void
    {
        $platform = $this->createPlatformAdmin();

        // Create promotion
        $code = 'SUMMER30_'.time();
        $createRes = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/marketing/promotions', [
                'code' => $code,
                'description' => '30% off summer campaign',
                'discount_type' => 'percent',
                'discount_value' => 30,
                'max_redemptions' => 100,
                'applicable_tiers' => ['plus', 'family'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', $code);

        $promoId = $createRes->json('data.id');

        // Toggle active
        $this->withToken($this->tokenFor($platform))
            ->postJson("/api/v1/platform/marketing/promotions/{$promoId}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Delete promotion
        $this->withToken($this->tokenFor($platform))
            ->deleteJson("/api/v1/platform/marketing/promotions/{$promoId}")
            ->assertOk();

        $this->assertDatabaseMissing('marketing_promotions', ['id' => $promoId]);
    }

    public function test_platform_admin_can_manage_announcement_campaigns(): void
    {
        $platform = $this->createPlatformAdmin();

        $res = $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/marketing/announcements', [
                'title' => 'Holiday Offer',
                'message' => 'Get 3 months free on annual plans',
                'cta_text' => 'Claim Deal',
                'cta_url' => '/billing',
                'tone' => 'success',
                'target_audience' => 'all',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Holiday Offer');

        $announcementId = $res->json('data.id');

        $this->withToken($this->tokenFor($platform))
            ->postJson("/api/v1/platform/marketing/announcements/{$announcementId}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withToken($this->tokenFor($platform))
            ->deleteJson("/api/v1/platform/marketing/announcements/{$announcementId}")
            ->assertOk();

        $this->assertDatabaseMissing('marketing_announcements', ['id' => $announcementId]);
    }

    public function test_platform_admin_can_manage_leads_and_export(): void
    {
        $platform = $this->createPlatformAdmin();

        $lead = MarketingLead::create([
            'email' => 'prospect_'.time().'@family.com',
            'name' => 'John Prospect',
            'source' => 'website',
            'status' => 'new',
        ]);

        $this->withToken($this->tokenFor($platform))
            ->patchJson("/api/v1/platform/marketing/leads/{$lead->id}", [
                'status' => 'contacted',
                'notes' => 'Followed up via sales demo call',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'contacted');

        // CSV export
        $exportRes = $this->withToken($this->tokenFor($platform))
            ->get('/api/v1/platform/marketing/leads/export');
        $exportRes->assertOk();
        $this->assertStringContainsString('text/csv', $exportRes->headers->get('Content-Type'));
    }

    public function test_platform_admin_can_manage_referral_settings(): void
    {
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->getJson('/api/v1/platform/marketing/referrals')
            ->assertOk()
            ->assertJsonPath('data.enabled', true);

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/marketing/referrals', [
                'enabled' => true,
                'reward_type' => 'credit_minor',
                'reward_value' => 1500,
                'referrer_bonus_cents' => 1500,
                'referee_discount_percent' => 25,
                'min_subscription_tier' => 'starter',
                'terms_markdown' => 'Give $15, Get $15',
            ])
            ->assertOk()
            ->assertJsonPath('data.reward_value', 1500);
    }

    public function test_public_user_can_subscribe_and_fetch_active_announcements(): void
    {
        MarketingAnnouncement::create([
            'title' => 'Live Public Banner '.time(),
            'message' => 'Active promotional message',
            'is_active' => true,
        ]);

        $uniqueEmail = 'visitor_'.time().'@family.org';

        $this->postJson('/api/v1/marketing/subscribe', [
            'email' => $uniqueEmail,
            'name' => 'Alice Visitor',
            'source' => 'landing_page',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('marketing_leads', [
            'email' => $uniqueEmail,
            'status' => 'new',
        ]);

        $this->getJson('/api/v1/marketing/announcements/active')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
