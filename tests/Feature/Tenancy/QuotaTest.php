<?php

namespace Tests\Feature\Tenancy;

use App\Models\Place;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Plans\QuotaService;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Plan limits: what the API reports, and what it actually refuses.
 *
 * The caps used to live only in the dashboard's presentation catalogue, so they
 * were decoration. These tests pin the numbers to behaviour.
 */
class QuotaTest extends TestCase
{
    use CreatesTenants;

    private function setTier(Tenant $tenant, string $tier): Tenant
    {
        // Inside the bypass: without it, RLS hides the row from the refresh.
        return $this->withBypass(function () use ($tenant, $tier): Tenant {
            $tenant->forceFill(['subscription_tier' => $tier])->save();

            return $tenant->refresh();
        });
    }

    public function test_the_household_reads_its_plan_limits_and_live_usage(): void
    {
        $tenant = $this->setTier($this->createTenant('Household A'), 'standard');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $this->withBypass(function () use ($tenant, $admin): void {
            TenantInvitation::withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenant->id,
                'email' => 'pending@example.com',
                'role' => 'member',
                'token' => 'token-abc',
                'expires_at' => now()->addDays(7),
            ]);

            $this->createRequest($tenant, $admin);
        });

        $payload = $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/tenant/quotas')
            ->assertOk()
            ->json();

        $this->assertSame('standard', $payload['plan']['key']);
        $this->assertSame(10, $payload['plan']['seat_cap']);
        $this->assertTrue($payload['enforced']);

        $this->assertSame(1, $payload['usage']['members']);
        $this->assertSame(1, $payload['usage']['seats_reserved']);
        $this->assertSame(1, $payload['usage']['requests_this_month']);

        $seats = collect($payload['meters'])->firstWhere('key', 'seats');

        $this->assertSame(2, $seats['used'], 'a pending invitation holds a seat');
        $this->assertSame(8, $seats['remaining']);
        $this->assertSame(20, $seats['percent']);
        $this->assertFalse($seats['at_cap']);
        $this->assertSame(1, $seats['detail']['reserved_by_invitations']);
    }

    public function test_the_mobile_surface_reports_the_same_limits(): void
    {
        $tenant = $this->createTenant('Household A');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $this->withToken($this->tokenFor($admin))
            ->getJson('/mobile/v1/tenant/quotas')
            ->assertOk()
            ->assertJsonPath('plan.key', 'pilot')
            ->assertJsonPath('plan.seat_cap', 5);
    }

    public function test_a_household_cannot_invite_past_its_seat_cap(): void
    {
        // Starter allows two seats: the admin plus one invitation.
        $tenant = $this->setTier($this->createTenant('Household A'), 'starter');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $token = $this->tokenFor($admin);

        $this->withToken($token)
            ->postJson('/api/v1/tenant/invitations', ['email' => 'first@example.com', 'role' => 'member'])
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/tenant/invitations', ['email' => 'second@example.com', 'role' => 'member'])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'This household has used all 2 seats on the Starter Family plan. '.
                'Remove a member or an invitation, or move to a larger plan.'
            );

        $this->withBypass(function () use ($tenant): void {
            $this->assertSame(
                1,
                TenantInvitation::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count()
            );
        });
    }

    public function test_accepting_an_invitation_is_refused_when_the_household_filled_up(): void
    {
        $tenant = $this->setTier($this->createTenant('Household A'), 'starter');
        $this->createMember($tenant, 'tenant_admin');
        $this->createMember($tenant, 'member');

        // An invitation issued before the household filled up.
        $invitation = $this->withBypass(fn (): TenantInvitation => TenantInvitation::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'email' => 'late@example.com',
            'role' => 'member',
            'token' => 'late-token',
            'expires_at' => now()->addDays(7),
        ]));

        $joiner = User::factory()->create(['email' => 'late@example.com']);

        $this->withToken($this->tokenFor($joiner))
            ->postJson('/api/v1/auth/invitations/accept', ['token' => $invitation->token])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'This household has used all 2 seats on the Starter Family plan. '.
                'Remove a member or an invitation, or move to a larger plan.'
            );

        $this->withBypass(function () use ($tenant): void {
            $this->assertSame(
                2,
                \App\Models\TenantUser::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count()
            );
        });
    }

    public function test_places_are_capped_by_the_plan(): void
    {
        $tenant = $this->setTier($this->createTenant('Household A'), 'starter');
        $admin = $this->createMember($tenant, 'tenant_admin');
        $token = $this->tokenFor($admin);

        foreach (['First', 'Second'] as $index => $name) {
            $this->withToken($token)->postJson('/api/v1/places', [
                'name' => $name,
                'latitude' => 23.81 + $index / 100,
                'longitude' => 90.41 + $index / 100,
                'radius_meters' => 300,
            ])->assertCreated();
        }

        $this->withToken($token)->postJson('/api/v1/places', [
            'name' => 'Third',
            'latitude' => 23.85,
            'longitude' => 90.45,
            'radius_meters' => 300,
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'This household has saved all 2 places on the Starter Family plan. '.
                'Remove a place, or move to a larger plan.'
            );

        $this->withBypass(fn () => $this->assertSame(2, Place::withoutGlobalScope('tenant')->count()));
    }

    public function test_the_monthly_request_allowance_is_enforced(): void
    {
        $tenant = $this->setTier($this->createTenant('Household A'), 'starter');
        $admin = $this->createMember($tenant, 'tenant_admin');

        // Starter includes 50 requests a month; fill it up directly.
        $this->withBypass(function () use ($tenant, $admin): void {
            for ($i = 0; $i < 50; $i++) {
                $this->createRequest($tenant, $admin, ['title' => "Request {$i}"]);
            }
        });

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/requests', ['title' => 'The one too many'])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'This household has created all 50 requests included in the Starter Family '.
                'plan this month. Nothing is lost — it resets at the start of next month.'
            );

        // The allowance is monthly, so last month's requests do not count.
        $this->withBypass(function () use ($tenant): void {
            \App\Models\ServiceRequest::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->update(['created_at' => now()->subMonthNoOverflow()]);
        });

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/requests', ['title' => 'A fresh month'])
            ->assertCreated();
    }

    public function test_an_unknown_tier_falls_back_to_the_default_plan_not_to_no_limits(): void
    {
        $tenant = $this->createTenant('Household A');
        $this->withBypass(fn () => $tenant->forceFill(['subscription_tier' => 'not-a-plan'])->save());

        // The API no longer accepts an unknown tier through the front door.
        $platform = $this->createPlatformAdmin();

        $this->withToken($this->tokenFor($platform))
            ->postJson('/api/v1/platform/tenants', [
                'name' => 'Typo Household',
                'subscription_tier' => 'not-a-plan',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subscription_tier']);

        // And a row that already holds one resolves to the default's limits.
        $plan = app(QuotaService::class)->planFor('not-a-plan');

        $this->assertSame('pilot', $plan['key']);
        $this->assertSame(5, $plan['seat_cap']);
        $this->assertSame(10, $plan['place_cap']);
    }

    public function test_enforcement_can_be_switched_off_for_seeding_and_provisioning(): void
    {
        config(['plans.enforce' => false]);

        $this->assertFalse(app(QuotaService::class)->enforcementEnabled());

        $tenant = $this->setTier($this->createTenant('Household A'), 'starter');

        // Seeding writes memberships directly, so a household larger than its plan
        // is possible by design — the platform is not a customer.
        $this->createMember($tenant, 'tenant_admin');
        $this->createMember($tenant, 'member');
        $this->createMember($tenant, 'member');

        $admin = $this->createMember($tenant, 'member');

        $this->withToken($this->tokenFor($admin))
            ->postJson('/api/v1/places', [
                'name' => 'Beyond the cap',
                'latitude' => 23.81,
                'longitude' => 90.41,
                'radius_meters' => 300,
            ])
            ->assertCreated();
    }

    public function test_the_denial_names_the_meter_at_its_cap(): void
    {
        $tenant = $this->setTier($this->createTenant('Household A'), 'starter');
        $admin = $this->createMember($tenant, 'tenant_admin');

        $this->withBypass(function () use ($tenant, $admin): void {
            $this->createRequest($tenant, $admin);
            for ($i = 0; $i < 49; $i++) {
                $this->createRequest($tenant, $admin, ['title' => "Filler {$i}"]);
            }
        });

        // Read through the bypass: the counts are RLS-filtered, so a bare call with
        // no tenant context would (correctly) see nothing.
        $report = $this->withBypass(fn (): array => app(QuotaService::class)->report($tenant));

        $this->assertContains('requests', $report['at_cap']);

        $requests = collect($report['meters'])->firstWhere('key', 'requests');

        $this->assertTrue($requests['at_cap']);
        $this->assertSame(0, $requests['remaining']);
        $this->assertSame(100, $requests['percent']);
    }
}
