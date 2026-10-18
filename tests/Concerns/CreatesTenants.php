<?php

namespace Tests\Concerns;

use App\Enums\Cadence;
use App\Enums\LedgerEntryDirection;
use App\Enums\LedgerEntrySource;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Item;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Models\LedgerMember;
use App\Models\Place;
use App\Models\RecurringRule;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Tenancy\TenantProvisioner;
use Illuminate\Support\Carbon;

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

    /**
     * A saved place, created the same way the other fixtures are: forceFill under
     * the bypass, because `tenant_id` is deliberately not mass-assignable.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createPlace(Tenant $tenant, string $name, array $attributes = []): Place
    {
        return $this->withBypass(function () use ($tenant, $name, $attributes): Place {
            $place = new Place;

            $place->forceFill(array_merge([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'radius_meters' => 300,
            ], $attributes))->save();

            return $place;
        });
    }

    protected function tokenFor(User $user): string
    {
        return $user->createToken('phpunit')->plainTextToken;
    }

    /**
     * A recurring rule, due today unless the attributes say otherwise.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createRecurringRule(Tenant $tenant, User $creator, array $attributes = []): RecurringRule
    {
        return $this->withBypass(function () use ($tenant, $creator, $attributes): RecurringRule {
            $rule = new RecurringRule;

            $rule->forceFill(array_merge([
                'tenant_id' => $tenant->id,
                'title' => 'Test rule',
                'cadence' => Cadence::Daily,
                'next_run_on' => Carbon::today()->toDateString(),
                'active' => true,
                'created_by' => $creator->id,
            ], $attributes))->save();

            return $rule;
        });
    }

    /**
     * A ledger with its owner's membership row, created the same way the other
     * fixtures are: under the bypass, because `tenant_id` and `owner_id` are the
     * database's business rather than the model's.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createLedger(Tenant $tenant, User $owner, string $name = 'Household ledger', array $attributes = []): Ledger
    {
        return $this->withBypass(function () use ($tenant, $owner, $name, $attributes): Ledger {
            $ledger = new Ledger;

            $ledger->forceFill(array_merge([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'owner_id' => $owner->id,
                'currency' => 'BDT',
            ], $attributes))->save();

            $this->addLedgerMember($ledger, $owner, 'owner');

            return $ledger;
        });
    }

    protected function addLedgerMember(Ledger $ledger, User $user, string $role = 'summary'): LedgerMember
    {
        return $this->withBypass(fn (): LedgerMember => LedgerMember::create([
            'tenant_id' => $ledger->tenant_id,
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'role' => $role,
        ]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createLedgerEntry(Ledger $ledger, User $creator, array $attributes = []): LedgerEntry
    {
        return $this->withBypass(fn (): LedgerEntry => LedgerEntry::create(array_merge([
            'tenant_id' => $ledger->tenant_id,
            'ledger_id' => $ledger->id,
            'amount_minor' => 10_000,
            'direction' => LedgerEntryDirection::Expense,
            'occurred_on' => Carbon::today()->toDateString(),
            'source' => LedgerEntrySource::Manual,
            'created_by' => $creator->id,
        ], $attributes)));
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
