<?php

namespace App\Messaging\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageTemplate extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'message_templates';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['variables' => 'array'];
    }
}
