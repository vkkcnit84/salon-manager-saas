<?php

namespace App\Catalog\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'products';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
