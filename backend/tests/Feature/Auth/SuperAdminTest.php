<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Tenancy\Actions\CreateTenantForOwner;
use App\Tenancy\Models\Plan;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
    }

    private function owner(string $mobile): User
    {
        $u = User::create(['name' => 'O', 'mobile' => $mobile]);
        (new CreateTenantForOwner)($u, 'Biz'.$mobile)->update(['plan_id' => Plan::where('code', 'pro')->value('id')]);

        return $u->fresh();
    }

    private function admin(): User
    {
        return User::where('email', 'a@x.test')->first()
            ?? tap(User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'secret123']), fn ($u) => $u->forceFill(['is_super_admin' => true])->save());
    }

    public function test_only_super_admin_reaches_admin_api(): void
    {
        Sanctum::actingAs($this->owner('9000000301'));
        $this->getJson('/api/v1/admin/tenants')->assertForbidden();
        $this->putJson('/api/v1/admin/roles/cashier/permissions', ['permissions' => []])->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/admin/overview')->assertOk()->assertJsonPath('tenants', 1);
        $this->getJson('/api/v1/admin/tenants')->assertOk()->assertJsonCount(1)->assertJsonPath('0.plan.code', 'pro');
    }

    public function test_suspending_a_tenant_blocks_its_users(): void
    {
        $o = $this->owner('9000000302');
        Sanctum::actingAs($o);
        $this->getJson('/api/v1/shops')->assertOk();

        Sanctum::actingAs($this->admin());
        $this->putJson('/api/v1/admin/tenants/'.$o->tenant_id, ['status' => 'suspended'])->assertOk()->assertJsonPath('status', 'suspended');

        Sanctum::actingAs($o);
        $this->getJson('/api/v1/shops')->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->putJson('/api/v1/admin/tenants/'.$o->tenant_id, ['status' => 'active', 'plan_id' => Plan::where('code', 'basic')->value('id')])->assertOk()->assertJsonPath('plan.code', 'basic');
    }

    public function test_role_permissions_are_editable_and_actually_enforced(): void
    {
        $o = $this->owner('9000000303');
        Sanctum::actingAs($o);
        $shop = $this->postJson('/api/v1/shops', ['name' => 'S'])->json();
        $svc = $this->postJson('/api/v1/services', ['name' => 'Cut', 'price_paise' => 10000, 'gst_bps' => 0])->json();
        $this->postJson('/api/v1/users', ['name' => 'Cash', 'mobile' => '9000000304', 'shop_id' => $shop['id'], 'role' => 'cashier'])->assertCreated();
        $cashier = User::where('mobile', '9000000304')->first();
        $h = ['X-Shop-Id' => $shop['id']];
        $bill = ['items' => [['type' => 'service', 'id' => $svc['id']]], 'pay_in_full' => 'cash'];

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/invoices', $bill, $h)->assertCreated();

        Sanctum::actingAs($this->admin());
        $this->putJson('/api/v1/admin/roles/shop_owner/permissions', ['permissions' => []])->assertStatus(422);
        $this->putJson('/api/v1/admin/roles/cashier/permissions', ['permissions' => ['customers.manage']])->assertOk();
        $this->getJson('/api/v1/admin/roles')->assertOk()->assertJsonFragment(['name' => 'cashier']);

        Sanctum::actingAs($cashier->fresh());
        $this->postJson('/api/v1/invoices', $bill, $h)->assertForbidden();
        $this->assertSame(['customers.manage'], Role::findByName('cashier', 'web')->permissions->pluck('name')->all());
    }
}
