<?php

namespace App\Staff\Services;

use App\Catalog\Models\Service;
use App\Shared\Support\Money;
use App\Staff\Models\Staff;
use App\Staff\Models\StaffServiceCommission;

/** Precedence: staff+service override -> service default -> staff default. Percent is in basis points. */
class CommissionResolver
{
    /** @return array{source:string,type:string,value:int} */
    public function rule(Staff $staff, ?Service $service): array
    {
        if ($service) {
            $o = StaffServiceCommission::where('staff_id', $staff->id)->where('service_id', $service->id)->first();
            if ($o) {
                return ['source' => 'staff_service', 'type' => $o->commission_type, 'value' => (int) $o->commission_value];
            }
            if ($service->commission_type) {
                return ['source' => 'service', 'type' => $service->commission_type, 'value' => (int) $service->commission_value];
            }
        }

        return ['source' => 'staff', 'type' => $staff->commission_type, 'value' => (int) $staff->commission_value];
    }

    /** Commission on a net (taxable) amount; fixed commissions apply once per unit. */
    public function amount(array $rule, Money $net, int $qty = 1): Money
    {
        return $rule['type'] === 'fixed'
            ? Money::paise($rule['value'] * $qty)
            : $net->percent($rule['value']);
    }
}
