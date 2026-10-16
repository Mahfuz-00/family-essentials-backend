<?php

namespace App\Policies;

use App\Models\Place;
use App\Models\TenantUser;
use App\Models\User;

/**
 * Saved places and their geofences are household infrastructure, so juniors can
 * read them (shopping lists depend on it) but not create, resize or delete.
 */
class PlacePolicy
{
    public function view(User $user, Place $place): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isJunior($user) === false;
    }

    public function update(User $user, Place $place): bool
    {
        return $this->isJunior($user) === false;
    }

    public function delete(User $user, Place $place): bool
    {
        return $this->isJunior($user) === false;
    }

    private function isJunior(User $user): bool
    {
        return TenantUser::query()
            ->where('user_id', $user->getKey())
            ->first()?->isJunior() === true;
    }
}
