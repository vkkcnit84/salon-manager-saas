<?php

namespace App\Admin\Http;

use App\Tenancy\Models\Plan;
use App\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Platform-level (cross-tenant) administration. Never reached by tenant users: see EnsureSuperAdmin. */
class AdminController extends Controller
{
    /** Roles whose grants are fixed by the platform and cannot be edited here. */
    private const LOCKED = ['super_admin', 'shop_owner'];

    public function overview()
    {
        return [
            'tenants' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'trial' => Tenant::where('status', 'trial')->count(),
            'suspended' => Tenant::where('status', 'suspended')->count(),
            'shops' => DB::table('shops')->whereNull('deleted_at')->count(),
            'users' => DB::table('users')->whereNull('deleted_at')->where('is_super_admin', false)->count(),
            'invoices' => DB::table('invoices')->count(),
            'gmv_paise' => (int) DB::table('invoices')->where('status', '!=', 'void')->sum('total_paise'),
            'mrr_paise' => (int) DB::table('tenants')->join('plans', 'plans.id', '=', 'tenants.plan_id')->where('tenants.status', 'active')->whereNull('tenants.deleted_at')->sum('plans.price_paise'),
        ];
    }

    public function tenants()
    {
        $shops = DB::table('shops')->whereNull('deleted_at')->selectRaw('tenant_id, count(*) c')->groupBy('tenant_id')->pluck('c', 'tenant_id');
        $users = DB::table('users')->whereNull('deleted_at')->selectRaw('tenant_id, count(*) c')->groupBy('tenant_id')->pluck('c', 'tenant_id');
        $inv = DB::table('invoices')->where('status', '!=', 'void')->selectRaw('tenant_id, count(*) c, sum(total_paise) s')->groupBy('tenant_id')->get()->keyBy('tenant_id');
        $owners = DB::table('users')->whereIn('id', Tenant::pluck('owner_user_id'))->get(['id', 'name', 'email', 'mobile'])->keyBy('id');

        return Tenant::with('plan:id,code,name')->latest('id')->get()->map(fn ($t) => [
            'id' => $t->id, 'business_name' => $t->business_name, 'status' => $t->status,
            'plan' => $t->plan, 'trial_ends_at' => $t->trial_ends_at, 'created_at' => $t->created_at,
            'owner' => $owners[$t->owner_user_id] ?? null,
            'shops' => (int) ($shops[$t->id] ?? 0), 'users' => (int) ($users[$t->id] ?? 0),
            'invoices' => (int) ($inv[$t->id]->c ?? 0), 'sales_paise' => (int) ($inv[$t->id]->s ?? 0),
        ]);
    }

    public function plans()
    {
        return Plan::orderBy('price_paise')->get();
    }

    public function updateTenant(Request $r, int $id)
    {
        $d = $r->validate([
            'plan_id' => ['sometimes', 'integer', 'exists:plans,id'],
            'status' => ['sometimes', Rule::in(['trial', 'active', 'suspended'])],
        ]);
        $tenant = Tenant::findOrFail($id);
        $tenant->update($d);

        return $tenant->load('plan:id,code,name');
    }

    public function roles()
    {
        $perms = Permission::orderBy('name')->pluck('name');

        return [
            'permissions' => $perms,
            'roles' => Role::with('permissions:id,name')->orderBy('id')->get()->map(fn ($role) => [
                'name' => $role->name,
                'locked' => in_array($role->name, self::LOCKED, true),
                'permissions' => $role->permissions->pluck('name')->sort()->values(),
            ]),
        ];
    }

    public function updateRole(Request $r, string $name)
    {
        abort_if(in_array($name, self::LOCKED, true), 422, 'This role is fixed by the platform.');
        $d = $r->validate(['permissions' => ['present', 'array'], 'permissions.*' => ['string', Rule::exists('permissions', 'name')]]);
        Role::findByName($name, 'web')->syncPermissions($d['permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->roles();
    }
}
