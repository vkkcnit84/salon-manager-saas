<?php

namespace Tests\Support;

use App\Customers\Models\Customer;
use App\Models\User;
use App\Tenancy\Actions\CreateTenantForOwner;
use App\Tenancy\Models\Plan;
use App\Tenancy\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Spatie\Permission\Models\Role;

trait MakesTenants
{
    protected function bootTenants(): void
    {
        $this->seed(RolesAndPlansSeeder::class);
        config(['messaging.listeners_enabled' => true, 'messaging.sync' => true]);
    }

    protected function owner(string $mobile, string $plan = 'pro', int $credits = 1000): User
    {
        $u = User::create(['name' => 'Owner '.$mobile, 'mobile' => $mobile, 'email' => $mobile.'@t.test', 'password' => 'secret123']);
        $t = (new CreateTenantForOwner)($u, 'Biz'.$mobile);
        $t->update(['plan_id' => Plan::where('code', $plan)->value('id'), 'message_credits' => $credits]);

        return $u->fresh();
    }

    protected function member(User $owner, string $mobile, string $role, int $shopId): User
    {
        $u = User::create(['tenant_id' => $owner->tenant_id, 'name' => ucfirst($role), 'mobile' => $mobile]);
        \DB::table('shop_user')->insert(['tenant_id' => $owner->tenant_id, 'shop_id' => $shopId, 'user_id' => $u->id]);
        setPermissionsTeamId($shopId);
        $u->syncRoles([Role::findByName($role, 'web')]);

        return $u->fresh();
    }

    protected function inTenant(User $u, callable $fn)
    {
        app(TenantContext::class)->set($u->tenant_id);
        try {
            return $fn();
        } finally {
            app(TenantContext::class)->clear();
        }
    }

    protected function customer(User $owner, string $name, string $mobile, bool $consent = true): Customer
    {
        return $this->inTenant($owner, fn () => Customer::create(['name' => $name, 'mobile' => $mobile, 'consent_whatsapp' => $consent, 'consent_at' => now()]));
    }
}
