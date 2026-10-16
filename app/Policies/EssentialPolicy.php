<?php

namespace App\Policies;

use App\Models\Essential;
use App\Models\TenantUser;
use App\Models\User;

/**
 * The pantry is household configuration, so juniors read it and restock it but
 * do not create, reconfigure or delete entries.
 */
class EssentialPolicy
{
    public function view(User $user, Essential $essential): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isJunior($user) === false;
    }

    public function update(User $user, Essential $essential): bool
    {
        return $this->isJunior($user) === false;
    }

    public function delete(User $user, Essential $essential): bool
    {
        return $this->isJunior($user) === false;
    }

    /**
     * Restocking is a shopping action: anyone in the household can do it.
     */
    public function restock(User $user, Essential $essential): bool
    {
        return true;
    }

    private function isJunior(User $user): bool
    {
        return TenantUser::query()
            ->where('user_id', $user->getKey())
            ->first()?->isJunior() === true;
    }
}
