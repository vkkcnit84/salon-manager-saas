<?php

namespace App\Messaging\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CampaignMessage extends Model
{
    use BelongsToTenant;

    protected $table = 'campaign_messages';

    protected $guarded = ['tenant_id'];

    public function customer()
    {
        return $this->belongsTo(\App\Customers\Models\Customer::class);
    }
}
