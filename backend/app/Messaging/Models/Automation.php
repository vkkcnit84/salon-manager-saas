<?php

namespace App\Messaging\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Automation extends Model
{
    use BelongsToTenant;

    protected $table = 'automations';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
