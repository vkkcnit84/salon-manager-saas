<?php

namespace App\Staff\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StaffServiceCommission extends Model
{
    use BelongsToTenant;

    protected $table = 'staff_service_commissions';

    public $timestamps = false;

    protected $guarded = ['tenant_id'];
}
