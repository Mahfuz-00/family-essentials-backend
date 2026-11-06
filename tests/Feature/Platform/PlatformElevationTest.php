<?php

namespace Tests\Feature\Platform;

use App\Models\Essential;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PlatformElevationTest extends TestCase
{
    use CreatesTenants;

    public function test_platform_system_settings_endpoints(): void
    {
        $platformAdmin = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platformAdmin))
            ->getJson('/api/v1/platform/settings/system')
            ->assertOk()
            ->assertJsonPath('data.app_name', 'FairShare');

        $this->withToken($this->tokenFor($platformAdmin))
            ->postJson('/api/v1/platform/settings/system', [
                'app_name' => 'FairShare Ops',
                'support_email' => 'ops@fairshare.app',
                'company_name' => 'FairShare Operations Inc.',
                'default_plan' => 'family_plus',
                'default_trial_days' => 30,
                'allow_registrations' => true,
                'require_email_verification' => true,
                'session_timeout_minutes' => 60,
                'max_members_free_tier' => 5,
                'maintenance_mode' => false,
                'maintenance_announcement' => 'Scheduled maintenance at midnight UTC',
            ])
            ->assertOk()
            ->assertJsonPath('data.app_name', 'FairShare Ops')
            ->assertJsonPath('data.default_plan', 'family_plus');
    }

    public function test_user_can_update_profile_and_password(): void
    {
        $tenant = $this->createTenant('Profile Test Household');
        $user = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($user))
            ->patchJson('/api/v1/auth/me', [
                'name' => 'Updated User Name',
                'email' => $user->email,
                'phone' => '+15551234567',
                'voice_opt_in' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated User Name')
            ->assertJsonPath('data.phone', '+15551234567');

        // Test password update with wrong current password
        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/auth/me/password', [
                'current_password' => 'wrong-pass',
                'password' => 'NewSecurePassword123!',
                'password_confirmation' => 'NewSecurePassword123!',
            ])
            ->assertStatus(422);

        // Test password update with correct current password ('password' from CreatesTenants)
        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/auth/me/password', [
                'current_password' => 'password',
                'password' => 'NewSecurePassword123!',
                'password_confirmation' => 'NewSecurePassword123!',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Password updated successfully.');

        $this->assertTrue(Hash::check('NewSecurePassword123!', $user->fresh()->password));
    }

    public function test_invoicing_created_after_payment_simulation(): void
    {
        $tenant = $this->createTenant('Billing Test Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $response = $this->withToken($this->tokenFor($user, $tenant))
            ->postJson('/api/v1/billing/upgrade', [
                'tier' => 'family_plus',
                'billing_period' => 'yearly',
                'payment_method' => 'card',
                'last4' => '4242',
            ])
            ->assertStatus(201);

        $invoiceId = $response->json('invoice.id');
        $this->assertNotNull($invoiceId);

        $this->withToken($this->tokenFor($user, $tenant))
            ->getJson('/api/v1/billing/invoices')
            ->assertOk()
            ->assertJsonFragment(['tier' => 'family_plus']);

        $invoice = Invoice::findOrFail($invoiceId);
        $this->withToken($this->tokenFor($user, $tenant))
            ->get("/api/v1/billing/invoices/{$invoice->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee($invoice->invoice_number);
    }

    public function test_calendar_feed_sync_ics(): void
    {
        $tenant = $this->createTenant('Calendar Test Household');
        $user = $this->createMember($tenant, 'member');

        $urlResp = $this->withToken($this->tokenFor($user, $tenant))
            ->getJson('/api/v1/calendar/feed-url')
            ->assertOk();

        $token = $urlResp->json('data.token');
        $this->assertNotEmpty($token);

        // Fetch calendar stream publicly (as subscribed by external calendar clients like Google/Apple Calendar)
        $icsResp = $this->get("/api/v1/calendar/feed/{$token}.ics")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $this->assertStringContainsString('BEGIN:VCALENDAR', $icsResp->getContent());
        $this->assertStringContainsString('PRODID:-//FairShare//Family Essentials Calendar//EN', $icsResp->getContent());
    }

    public function test_nlp_dispatcher_quick_add_parsing(): void
    {
        $tenant = $this->createTenant('NLP Dispatch Household');
        $user = $this->createMember($tenant, 'tenant_admin');
        $sarah = $this->createMember($tenant, 'member', ['name' => 'Sarah Connor']);

        $resp = $this->withToken($this->tokenFor($user, $tenant))
            ->postJson('/api/v1/requests/parse-nlp', [
                'text' => 'Pick up organic whole milk at Trader Joe\'s tomorrow 5pm for Sarah ASAP',
            ])
            ->assertOk();

        $parsed = $resp->json('parsed');
        $this->assertEquals('Organic whole milk', $parsed['title']);
        $this->assertEquals('groceries', $parsed['category']);
        $this->assertEquals('urgent', $parsed['priority']);
        $this->assertEquals("Trader Joe's", $parsed['store_name']);
        $this->assertEquals($sarah->id, $parsed['assignee_id']);
        $this->assertNotNull($parsed['due_at']);
    }

    public function test_optical_receipt_scanning_and_restock_suggestion(): void
    {
        $tenant = $this->createTenant('Receipt Test Household');
        $user = $this->createMember($tenant, 'tenant_admin');

        $essential = $this->withBypass(fn () => Essential::create([
            'tenant_id' => $tenant->id,
            'name' => 'Organic Whole Milk 1 Gal',
            'category' => 'groceries',
            'stock_level' => 0,
            'threshold' => 1,
            'is_active' => true,
        ]));

        $ledger = $this->withBypass(function () use ($tenant, $user) {
            $l = new Ledger();
            $l->tenant_id = $tenant->id;
            $l->name = 'Household Shared Pool';
            $l->owner_id = $user->id;
            $l->currency = 'USD';
            $l->save();

            \App\Models\LedgerMember::create([
                'tenant_id' => $tenant->id,
                'ledger_id' => $l->id,
                'user_id' => $user->id,
                'role' => \App\Enums\LedgerRole::Owner,
            ]);
            return $l;
        });

        $resp = $this->withToken($this->tokenFor($user, $tenant))
            ->postJson('/api/v1/ledger/receipts/scan', [
                'auto_record' => true,
                'ledger_id' => $ledger->id,
            ])
            ->assertOk();

        $this->assertGreaterThan(0, $resp->json('total_minor'));
        $this->assertNotEmpty($resp->json('suggested_restocks'));
        $this->assertEquals($essential->id, $resp->json('suggested_restocks.0.essential_id'));
        $this->assertNotNull($resp->json('recorded_entry'));
    }
}
