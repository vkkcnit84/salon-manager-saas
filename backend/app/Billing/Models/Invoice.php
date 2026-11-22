<?php

namespace App\Billing\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToTenant;

    protected $table = 'invoices';

    protected $guarded = ['tenant_id'];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function customer()
    {
        return $this->belongsTo(\App\Customers\Models\Customer::class);
    }
}
