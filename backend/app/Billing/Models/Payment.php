<?php

namespace App\Billing\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToTenant;

    protected $table = 'payments';

    protected $guarded = ['tenant_id'];
}
