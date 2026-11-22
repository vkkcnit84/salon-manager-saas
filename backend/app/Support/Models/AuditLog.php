<?php

namespace App\Support\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToTenant;

    protected $table = 'audit_logs';

    protected $guarded = ['tenant_id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
