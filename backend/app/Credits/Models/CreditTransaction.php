<?php

namespace App\Credits\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CreditTransaction extends Model
{
    use BelongsToTenant;

    protected $table = 'credit_transactions';

    protected $guarded = ['tenant_id'];

    public const UPDATED_AT = null;
}
