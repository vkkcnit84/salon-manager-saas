<?php

namespace App\Expenses\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use BelongsToTenant;

    protected $table = 'expenses';

    protected $guarded = ['tenant_id'];

    protected function casts(): array
    {
        return ['spent_on' => 'date:Y-m-d'];
    }
}
