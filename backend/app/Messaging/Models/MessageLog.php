<?php

namespace App\Messaging\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    use BelongsToTenant;

    protected $table = 'message_log';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['buttons' => 'array', 'context' => 'array'];
    }
}
