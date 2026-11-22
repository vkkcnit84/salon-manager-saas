<?php

namespace App\Staff\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'staff';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function shops()
    {
        return $this->belongsToMany(\App\Shops\Models\Shop::class, 'shop_staff');
    }
}
