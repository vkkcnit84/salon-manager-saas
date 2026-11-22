<?php

namespace App\Tenancy\Models;

use App\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Global tenant scope. Fails closed: with no tenant in context, queries return nothing
 * and creating a record throws, rather than silently crossing tenants.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = app(TenantContext::class)->tenantId();
            $table = $query->getModel()->getTable();
            $tenantId === null
                ? $query->whereRaw('1 = 0')
                : $query->where("{$table}.tenant_id", $tenantId);
        });

        static::creating(function (Model $model) {
            $tenantId = app(TenantContext::class)->tenantId();
            if ($model->tenant_id === null) {
                $tenantId ?? throw new \LogicException('No tenant in context.');
                $model->tenant_id = $tenantId;
            } elseif ($tenantId !== null && (int) $model->tenant_id !== $tenantId) {
                throw new \LogicException('Cross-tenant write blocked.');
            }
        });
    }
}
