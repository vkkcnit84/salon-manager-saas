<?php

namespace App\Mobile\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    use BelongsToTenant;

    protected $table = 'device_tokens';

    protected $guarded = ['tenant_id'];
}
