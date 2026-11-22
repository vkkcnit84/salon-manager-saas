<?php

namespace App\Subscriptions\Services;

use App\Tenancy\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Plan limits are enforced here, called from mutation actions (not only the UI). */
class PlanLimits
{
    public function assertCanAddShop(Tenant $tenant, int $currentShops): void
    {
        $this->assert($tenant, 'max_shops', $currentShops, 'shops');
    }

    public function assertCanAddStaff(Tenant $tenant, int $currentStaff): void
    {
        $this->assert($tenant, 'max_staff', $currentStaff, 'staff members');
    }

    private function assert(Tenant $tenant, string $column, int $current, string $label): void
    {
        $plan = $tenant->plan ?? throw new HttpException(402, 'No plan assigned.');
        if ($current >= $plan->{$column}) {
            throw new HttpException(402, "Your {$plan->name} plan allows up to {$plan->{$column}} {$label}. Upgrade to add more.");
        }
    }
}
