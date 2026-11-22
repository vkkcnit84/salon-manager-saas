<?php

namespace App\Billing\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use BelongsToTenant;

    protected $table = 'invoice_items';

    public $timestamps = false;

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['commission_snapshot' => 'array'];
    }
}
