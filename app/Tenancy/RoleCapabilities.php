<?php

namespace App\Tenancy;

use App\Models\TenantUser;
use App\Models\User;

/**
 * Reads the role capability matrix declared in config/roles.php.
 *
 * Everything that needs to know "may this role do X" goes through here — the
 * policies, the Roles API the dashboard renders, and the guards in the member
 * and invitation controllers — so a role rule is written down exactly once.
 */
class RoleCapabilities
{
    /**
     * @return array<string, array{label: string, detail: string}>
     */
    public static function roles(): array
    {
        return config('roles.roles', []);
    }

    /**
     * @return array<string, array{label: string, detail: string, roles: array<int, string>}>
     */
    public static function capabilities(): array
    {
        return config('roles.capabilities', []);
    }

    /**
     * @return array<int, string>
     */
    public static function privilegedRoles(): array
    {
        return config('roles.privileged_roles', []);
    }

    public static function isPrivilegedRole(?string $role): bool
    {
        return $role !== null && in_array($role, self::privilegedRoles(), true);
    }

    public static function allows(string $capability, ?string $role): bool
    {
        if ($role === null) {
            return false;
        }

        $allowed = self::capabilities()[$capability]['roles'] ?? [];

        return in_array($role, $allowed, true);
    }

    /**
     * Capability => role => bool, ready for the dashboard to render as a table.
     *
     * @return array<int, array{key: string, label: string, detail: string, access: array<string, bool>}>
     */
    public static function matrix(): array
    {
        $roles = array_keys(self::roles());
        $rows = [];

        foreach (self::capabilities() as $key => $capability) {
            $access = [];

            foreach ($roles as $role) {
                $access[$role] = in_array($role, $capability['roles'], true);
            }

            $rows[] = [
                'key' => $key,
                'label' => $capability['label'],
                'detail' => $capability['detail'],
                'access' => $access,
            ];
        }

        return $rows;
    }

    /**
     * The caller's role in the current tenant, or null when they have none.
     * TenantUser carries the tenant scope, so this fails closed without context.
     */
    public static function roleOf(?User $user): ?string
    {
        return TenantUser::roleFor($user);
    }
}
