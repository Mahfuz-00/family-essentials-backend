<?php

namespace Database\Seeders;

use App\Models\User;
use App\Tenancy\TenantProvisioner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a platform administrator plus two isolated demo households, each
     * with an admin and a second member.
     */
    public function run(): void
    {
        $provisioner = app(TenantProvisioner::class);

        $platform = User::factory()->create([
            'name' => 'Alex Thorne',
            'email' => 'platform@familyessentials.app',
            'phone' => '+15550000001',
        ]);
        $platform->forceFill([
            'is_platform_admin' => true,
            'phone_verified_at' => now(),
        ])->save();

        // --- Household A ---------------------------------------------------
        $tanvir = User::factory()->create([
            'name' => 'Tanvir Rahman',
            'email' => 'tanvir@familyessentials.app',
            'phone' => '+15550000002',
        ]);
        $tanvir->forceFill(['phone_verified_at' => now()])->save();
        $tanvirHousehold = $provisioner->provision('The Tanvir Household', $tanvir, status: 'active');
        $tanvir->forceFill(['active_tenant_id' => $tanvirHousehold->id])->save();

        $sarah = User::factory()->create([
            'name' => 'Sarah Rahman',
            'email' => 'sarah@familyessentials.app',
            'phone' => '+15550000004',
        ]);
        $sarah->forceFill([
            'phone_verified_at' => now(),
            'active_tenant_id' => $tanvirHousehold->id,
        ])->save();
        $provisioner->addMember($tanvirHousehold, $sarah, 'member');

        // --- Household B ---------------------------------------------------
        $bradley = User::factory()->create([
            'name' => 'Alex Bradley',
            'email' => 'bradley@familyessentials.app',
            'phone' => '+15550000003',
        ]);
        $bradley->forceFill(['phone_verified_at' => now()])->save();
        $bradleyHousehold = $provisioner->provision('The Bradley Household', $bradley, status: 'active');
        $bradley->forceFill(['active_tenant_id' => $bradleyHousehold->id])->save();

        $elena = User::factory()->create([
            'name' => 'Elena Bradley',
            'email' => 'elena@familyessentials.app',
            'phone' => '+15550000005',
        ]);
        $elena->forceFill([
            'phone_verified_at' => now(),
            'active_tenant_id' => $bradleyHousehold->id,
        ])->save();
        $provisioner->addMember($bradleyHousehold, $elena, 'member');
    }
}
