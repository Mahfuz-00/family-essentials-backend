<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'user_id', 'role', 'status', 'invited_by', 'joined_at'])]
class TenantUser extends Model
{
    use BelongsToTenant;

    /**
     * Household roles.
     *
     *  tenant_admin - full control, including roles and destructive actions
     *  co_admin     - runs the household day to day, but cannot change roles
     *  member       - creates, claims and completes requests
     *  junior       - creates requests and completes their own; cannot dispatch
     */
    public const ROLES = ['tenant_admin', 'co_admin', 'member', 'junior'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Administrator of any rank: full tenant-admin surfaces.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['tenant_admin', 'co_admin'], true);
    }

    /**
     * Only the primary administrator may change roles or remove members.
     */
    public function canManageMembers(): bool
    {
        return $this->role === 'tenant_admin';
    }

    public function isJunior(): bool
    {
        return $this->role === 'junior';
    }
}
