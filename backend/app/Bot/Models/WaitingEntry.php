<?php

namespace App\Bot\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WaitingEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'waiting_queue';

    protected $guarded = ['tenant_id'];

    public function customer()
    {
        return $this->belongsTo(\App\Customers\Models\Customer::class);
    }
}
