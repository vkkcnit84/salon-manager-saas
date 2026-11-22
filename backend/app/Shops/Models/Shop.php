<?php

namespace App\Shops\Models;

use App\Tenancy\Models\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = ['tenant_id'];

    protected static function booted(): void
    {
        static::creating(fn (self $s) => $s->code ??= self::newCode());
    }

    /** Short, unambiguous, globally unique public code (used in the QR check-in text). */
    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)];
            }
        } while (static::withoutGlobalScopes()->withTrashed()->where('code', $code)->exists() || \DB::table('shops')->where('code', $code)->exists());

        return $code;
    }

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
