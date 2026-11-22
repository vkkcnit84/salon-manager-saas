<?php

namespace App\Catalog\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ShopServicePrice extends Model
{
    use BelongsToTenant;

    protected $table = 'shop_service_prices';

    protected $guarded = ['tenant_id'];
}
