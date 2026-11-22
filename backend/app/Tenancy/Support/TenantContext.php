<?php

namespace App\Tenancy\Support;

/** Request-scoped holder of the current tenant and shop. Bound as a scoped singleton. */
final class TenantContext
{
    private ?int $tenantId = null;

    private ?int $shopId = null;

    public function set(int $tenantId, ?int $shopId = null): void
    {
        $this->tenantId = $tenantId;
        $this->shopId = $shopId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->shopId = null;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId;
    }

    public function shopId(): ?int
    {
        return $this->shopId;
    }
}
