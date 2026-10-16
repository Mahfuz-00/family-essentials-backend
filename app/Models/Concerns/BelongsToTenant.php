<?php

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies the tenant global scope and stamps `tenant_id` on create.
 *
 * Fails closed: with no tenant context and no bypass, the scope matches nothing
 * (mirroring the PostgreSQL RLS default-deny behaviour).
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);

            if ($context->isBypassed()) {
                return;
            }

            if ($context->id() !== null) {
                $builder->where($builder->qualifyColumn('tenant_id'), $context->id());
            } else {
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($model->getAttribute('tenant_id') === null && $context->id() !== null) {
                $model->setAttribute('tenant_id', $context->id());
            }
        });
    }
}
