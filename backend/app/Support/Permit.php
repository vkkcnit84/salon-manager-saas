<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class Permit
{
    /** Owner always; otherwise any of the user's per-shop roles must grant the permission (checked server-side every time). */
    public static function userCan(User $u, string $permission): bool
    {
        if ($u->is_owner) {
            return true;
        }

        return DB::table('model_has_roles as m')
            ->join('role_has_permissions as rp', 'rp.role_id', '=', 'm.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('m.model_id', $u->id)->where('m.model_type', $u->getMorphClass())->where('p.name', $permission)->exists();
    }

    public static function roleOf(User $u): string
    {
        if ($u->is_owner) {
            return 'owner';
        }
        $role = DB::table('model_has_roles as m')->join('roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.model_id', $u->id)->where('m.model_type', $u->getMorphClass())->pluck('r.name');

        return $role->contains('manager') ? 'manager' : ($role->first() ?? 'staff');
    }
}
