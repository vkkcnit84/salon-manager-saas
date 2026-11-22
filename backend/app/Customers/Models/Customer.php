<?php

namespace App\Customers\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'customers';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['consent_whatsapp' => 'boolean', 'consent_sms' => 'boolean', 'consent_at' => 'datetime', 'opted_out_at' => 'datetime', 'tags' => 'array', 'birthday' => 'date:Y-m-d', 'anniversary' => 'date:Y-m-d'];
    }

    /** The only sanctioned way to pick message recipients: consented on the channel and not opted out. */
    public function scopeContactable($q, string $channel = 'whatsapp')
    {
        return $q->whereNull('opted_out_at')->where($channel === 'sms' ? 'consent_sms' : 'consent_whatsapp', true);
    }
}
