<?php

namespace App\Models;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'status', 'subscription_tier', 'settings', 'trial_ends_at', 'suspended_at'])]
class Tenant extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);

            if ($context->isBypassed()) {
                return;
            }

            if ($context->id() !== null) {
                $builder->where($builder->qualifyColumn('id'), $context->id());
            } else {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
