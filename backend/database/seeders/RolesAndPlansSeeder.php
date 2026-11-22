<?php

namespace Database\Seeders;

use App\Tenancy\Models\Plan;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPlansSeeder extends Seeder
{
    /** Roles per the spec. Shop Owner is tenant-wide; others are assigned per shop team. */
    public const ROLES = [
        'super_admin' => ['tenants.manage', 'plans.manage', 'support.access'],
        'shop_owner' => ['*'],
        'manager' => ['billing.create', 'billing.edit', 'billing.refund', 'customers.manage', 'attendance.manage', 'reports.daily', 'discount.override'],
        'cashier' => ['billing.create', 'customers.manage', 'reports.today'],
        'staff' => ['own.earnings.view'],
    ];

    public function run(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(self::ROLES)->flatten()->reject(fn ($p) => $p === '*')->unique();
        $all->push('commission.view', 'payouts.manage', 'plan.settings', 'campaigns.manage', 'reports.all', 'pricing.manage', 'staff.manage');
        foreach ($all->unique() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // findOrCreate populates the permission cache before all rows exist; reset so role sync sees them.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $role => $perms) {
            $r = Role::findOrCreate($role, 'web');
            $r->syncPermissions($perms === ['*'] ? Permission::all() : $perms);
        }

        foreach ([
            ['code' => 'free', 'name' => 'Free', 'max_shops' => 1, 'max_staff' => 3, 'monthly_message_credits' => 0, 'price_paise' => 0],
            ['code' => 'basic', 'name' => 'Basic', 'max_shops' => 2, 'max_staff' => 10, 'monthly_message_credits' => 500, 'price_paise' => 49900],
            ['code' => 'pro', 'name' => 'Pro', 'max_shops' => 20, 'max_staff' => 200, 'monthly_message_credits' => 3000, 'price_paise' => 149900],
        ] as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
