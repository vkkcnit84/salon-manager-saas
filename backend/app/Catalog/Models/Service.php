<?php

namespace App\Catalog\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'services';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
