<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantProvisioner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * A platform administrator, two isolated households, and demo content.
     *
     * Safe to run more than once: every account and household is looked up
     * before it is created, because `php artisan db:seed` is a thing people run
     * again after a migration.
     */
    public function run(): void
    {
        $provisioner = app(TenantProvisioner::class);

        $platform = $this->user('Alex Thorne', 'platform@familyessentials.app', '+15550000001');
        $platform->forceFill(['is_platform_admin' => true])->save();

        // --- Household A ---------------------------------------------------
        [$tanvir, $tanvirHousehold] = $this->household(
            $provisioner,
            'The Tanvir Household',
            'Tanvir Rahman',
            'tanvir@familyessentials.app',
            '+15550000002',
        );
        $this->member($provisioner, $tanvirHousehold, 'Sarah Rahman', 'sarah@familyessentials.app', '+15550000004');

        // --- Household B ---------------------------------------------------
        [$bradley, $bradleyHousehold] = $this->household(
            $provisioner,
            'The Bradley Household',
            'Alex Bradley',
            'bradley@familyessentials.app',
            '+15550000003',
        );
        $this->member($provisioner, $bradleyHousehold, 'Elena Bradley', 'elena@familyessentials.app', '+15550000005');

        $this->call(DemoDataSeeder::class);
    }

    private function user(string $name, string $email, string $phone): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            return $user;
        }

        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ]);

        $user->forceFill(['phone_verified_at' => now()])->save();

        return $user;
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function household(
        TenantProvisioner $provisioner,
        string $name,
        string $ownerName,
        string $email,
        string $phone,
    ): array {
        $owner = $this->user($ownerName, $email, $phone);

        // Bypassed: the tenants table is RLS-protected and a seeder has no tenant
        // context yet.
        $tenant = Tenancy::bypass(
            fn (): ?Tenant => Tenant::query()->where('name', $name)->first()
        );

        $tenant ??= $provisioner->provision($name, $owner, status: 'active');

        $owner->forceFill(['active_tenant_id' => $tenant->id])->save();

        return [$owner, $tenant];
    }

    private function member(
        TenantProvisioner $provisioner,
        Tenant $tenant,
        string $name,
        string $email,
        string $phone,
    ): User {
        $user = $this->user($name, $email, $phone);
        $user->forceFill(['active_tenant_id' => $tenant->id])->save();

        $alreadyAMember = Tenancy::bypass(
            fn (): bool => TenantUser::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->exists()
        );

        if (! $alreadyAMember) {
            $provisioner->addMember($tenant, $user, 'member');
        }

        return $user;
    }
}
