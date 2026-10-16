<?php

namespace App\Policies;

use App\Models\TenantUser;
use App\Models\User;
use App\Tenancy\RoleCapabilities;

/**
 * Membership rules for a household.
 *
 * Policies are keyed to TenantUser because the subject is a person's *membership*
 * of the household, not the user record itself.
 */
class TenantUserPolicy
{
    /**
     * Reading the household roster. Open to any active member, because the mobile
     * app builds its assignee picker from it — stated here rather than left
     * implicit in the controller.
     */
    public function viewAny(User $user): bool
    {
        return RoleCapabilities::roleOf($user) !== null;
    }

    /**
     * Issuing an invitation for a non-admin role.
     */
    public function invite(User $user): bool
    {
        return RoleCapabilities::allows('invite_members', RoleCapabilities::roleOf($user));
    }

    /**
     * Changing a role, removing a member, or granting an admin role.
     */
    public function manageRoles(User $user): bool
    {
        return RoleCapabilities::allows('manage_roles', RoleCapabilities::roleOf($user));
    }

    /**
     * Removing someone else. Never yourself — that is how a household loses its
     * last administrator.
     */
    public function remove(User $user, User $target): bool
    {
        return $this->manageRoles($user) && ! $user->is($target);
    }
}
