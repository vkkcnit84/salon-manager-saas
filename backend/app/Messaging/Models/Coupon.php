<?php

namespace App\Messaging\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use BelongsToTenant;

    protected $table = 'coupons';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'active' => 'boolean'];
    }
}
