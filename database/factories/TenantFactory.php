<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * The columns `tenants` insists on are the name and a unique slug; everything
     * else has a schema default. A `trial` household on the `pilot` tier is what
     * provisioning produces when nothing else is asked for.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $base = Str::slug($name) ?: 'household';

        return [
            'name' => $name,
            'slug' => $base.'-'.Str::lower(Str::random(5)),
            'status' => 'trial',
            'subscription_tier' => 'pilot',
            'settings' => null,
            'trial_ends_at' => now()->addDays(14),
            'suspended_at' => null,
        ];
    }

    /**
     * Creating a tenant is a provisioning operation.
     *
     * `tenants` is `FORCE ROW LEVEL SECURITY` and its policy reads
     * `app.current_tenant`, so the insert runs under the same bypass
     * {@see \App\Tenancy\TenantProvisioner} uses. Without it a plain
     * `Tenant::factory()->create()` fails with a row-level-security policy
     * violation that says nothing about why.
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        return Tenancy::bypass(fn () => parent::create($attributes, $parent));
    }

    /**
     * A household that has finished its trial.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => 'active',
            'trial_ends_at' => null,
        ]);
    }

    /**
     * A household the landlord console has switched off.
     */
    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);
    }
}
