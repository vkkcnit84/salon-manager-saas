<?php

namespace App\Assistant\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    use BelongsToTenant;

    protected $table = 'ai_usage';

    protected $guarded = ['tenant_id'];

    public const UPDATED_AT = null;
}
