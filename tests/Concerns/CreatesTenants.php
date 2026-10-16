<?php

namespace Tests\Concerns;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Item;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Tenancy\TenantProvisioner;

trait CreatesTenants
{
    protected function createTenant(string $name, string $status = 'active'): Tenant
    {
        return $this->withBypass(
            fn (): Tenant => app(TenantProvisioner::class)->provision($name, null, status: $status),
        );
    }

    protected function attachToTenant(Tenant $tenant, User $user, string $role = 'member'): TenantUser
    {
        return $this->withBypass(fn (): TenantUser => TenantUser::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]));
    }

    protected function createMember(
        Tenant $tenant,
        string $role = 'tenant_admin',
        array $userAttributes = [],
    ): User {
        return $this->withBypass(function () use ($tenant, $role, $userAttributes): User {
            $user = User::factory()->create($userAttributes);

            TenantUser::withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $user->forceFill(['active_tenant_id' => $tenant->id])->save();

            return $user;
        });
    }

    protected function createPlatformAdmin(array $userAttributes = []): User
    {
        return $this->withBypass(function () use ($userAttributes): User {
            $user = User::factory()->create($userAttributes);
            $user->forceFill(['is_platform_admin' => true])->save();

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createRequest(Tenant $tenant, User $requester, array $attributes = []): ServiceRequest
    {
        return $this->withBypass(function () use ($tenant, $requester, $attributes): ServiceRequest {
            $request = new ServiceRequest;

            $request->forceFill(array_merge([
                'tenant_id' => $tenant->id,
                'title' => 'Test request',
                'requested_by' => $requester->id,
                'status' => RequestStatus::Pending,
            ], $attributes))->save();

            return $request;
        });
    }

    protected function createCatalogItem(Tenant $tenant, string $name, array $attributes = []): Item
    {
        return $this->withBypass(function () use ($tenant, $name, $attributes): Item {
            $item = new Item;

            $item->forceFill(array_merge([
                'tenant_id' => $tenant->id,
                'name' => $name,
            ], $attributes))->save();

            return $item;
        });
    }

    protected function tokenFor(User $user): string
    {
        return $user->createToken('phpunit')->plainTextToken;
    }

    protected function optInForVoice(User $user, string $phone = '+15550009999'): User
    {
        $user->forceFill([
            'voice_opt_in' => true,
            'phone' => $phone,
            'phone_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * An overdue, high-priority, assigned request - the shape voice escalation targets.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createEscalationCandidate(Tenant $tenant, User $assignee, array $attributes = []): ServiceRequest
    {
        return $this->createRequest($tenant, $assignee, array_merge([
            'title' => 'Overdue high priority',
            'priority' => RequestPriority::High,
            'status' => RequestStatus::Pending,
            'due_at' => now()->subHour(),
            'assignee_id' => $assignee->getKey(),
        ], $attributes));
    }
}
