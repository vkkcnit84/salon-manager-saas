<?php

namespace App\Catalog\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'service_categories';

    protected $guarded = ['tenant_id'];
}
