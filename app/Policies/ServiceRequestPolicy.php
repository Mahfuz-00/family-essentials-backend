<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\TenantUser;
use App\Models\User;

class ServiceRequestPolicy
{
    /**
     * Tenant scoping already guarantees the user belongs to the request's tenant.
     */
    public function view(User $user, ServiceRequest $request): bool
    {
        return true;
    }

    public function update(User $user, ServiceRequest $request): bool
    {
        return $this->isRequester($user, $request) || $this->isTenantAdmin($user);
    }

    public function cancel(User $user, ServiceRequest $request): bool
    {
        return $this->update($user, $request);
    }

    /**
     * Dispatching work to another member is not a junior capability: juniors
     * raise requests and complete their own, they do not assign.
     */
    public function assign(User $user, ServiceRequest $request): bool
    {
        return ! $this->isJunior($user) && $this->update($user, $request);
    }

    /**
     * May this user hand work to somebody else at all? Used when creating a
     * request with an assignee, where there is no persisted request to inspect.
     */
    public function dispatch(User $user): bool
    {
        return ! $this->isJunior($user);
    }

    /**
     * Manually triggering a voice escalation is privileged (requester or admin).
     */
    public function escalate(User $user, ServiceRequest $request): bool
    {
        return ! $this->isJunior($user) && $this->update($user, $request);
    }

    /**
     * Accept / decline / start / complete: the assignee, or anyone if unassigned.
     */
    public function respond(User $user, ServiceRequest $request): bool
    {
        return $request->assignee_id === null
            || (int) $request->assignee_id === (int) $user->getKey();
    }

    private function isRequester(User $user, ServiceRequest $request): bool
    {
        return (int) $request->requested_by === (int) $user->getKey();
    }

    private function isTenantAdmin(User $user): bool
    {
        return $this->membership($user)?->isAdmin() === true;
    }

    private function isJunior(User $user): bool
    {
        return $this->membership($user)?->isJunior() === true;
    }

    private function membership(User $user): ?TenantUser
    {
        return TenantUser::query()->where('user_id', $user->getKey())->first();
    }
}
