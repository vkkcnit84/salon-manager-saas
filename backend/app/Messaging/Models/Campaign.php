<?php

namespace App\Messaging\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use BelongsToTenant;

    protected $table = 'campaigns';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'meta' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function template()
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }
}
