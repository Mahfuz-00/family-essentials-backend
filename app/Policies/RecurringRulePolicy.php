<?php

namespace App\Policies;

use App\Models\RecurringRule;
use App\Models\TenantUser;
use App\Models\User;

/**
 * Recurring rules are household configuration, so they follow the pantry's
 * shape: everyone reads them, juniors do not create or reconfigure them.
 */
class RecurringRulePolicy
{
    public function view(User $user, RecurringRule $rule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return ! $this->isJunior($user);
    }

    public function update(User $user, RecurringRule $rule): bool
    {
        return ! $this->isJunior($user);
    }

    public function delete(User $user, RecurringRule $rule): bool
    {
        return ! $this->isJunior($user);
    }

    private function isJunior(User $user): bool
    {
        return TenantUser::query()
            ->where('user_id', $user->getKey())
            ->first()?->isJunior() === true;
    }
}
