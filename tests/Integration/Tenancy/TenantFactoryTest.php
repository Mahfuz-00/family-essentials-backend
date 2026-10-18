<?php

namespace Tests\Integration\Tenancy;

use App\Models\Tenant;
use Tests\TestCase;

/**
 * `Tenant::factory()` used to be a trap: the trait was there, the factory was
 * not, so the first test to reach for it died with a class-not-found rather than
 * a useful message. These tests are what keeps it honest.
 */
class TenantFactoryTest extends TestCase
{
    public function test_it_produces_a_tenant_the_database_accepts(): void
    {
        // No tenant context and no test-side bypass: creating a tenant is a
        // provisioning operation and the factory has to arrange the RLS bypass
        // the provisioner uses, or the insert is refused by the policy.
        $tenant = Tenant::factory()->create();

        $this->assertNotNull($tenant->getKey());
        $this->assertNotEmpty($tenant->name);
        $this->assertNotEmpty($tenant->slug);

        $this->withBypass(function () use ($tenant): void {
            $this->assertDatabaseHas('tenants', [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'status' => 'trial',
                'subscription_tier' => 'pilot',
            ]);
        });
    }

    public function test_two_tenants_get_different_slugs(): void
    {
        $one = Tenant::factory()->create();
        $other = Tenant::factory()->create();

        $this->assertNotSame($one->slug, $other->slug);
    }

    public function test_the_default_is_a_trial_household_on_the_pilot_tier(): void
    {
        $tenant = Tenant::factory()->make();

        $this->assertSame('trial', $tenant->status);
        $this->assertSame('pilot', $tenant->subscription_tier);
        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertNull($tenant->suspended_at);
    }

    public function test_the_named_states_produce_an_active_and_a_suspended_household(): void
    {
        $active = Tenant::factory()->active()->create();
        $suspended = Tenant::factory()->suspended()->create();

        $this->assertSame('active', $active->status);
        $this->assertNull($active->trial_ends_at);

        $this->assertSame('suspended', $suspended->status);
        $this->assertNotNull($suspended->suspended_at);
        $this->assertTrue($suspended->isSuspended());
    }
}
