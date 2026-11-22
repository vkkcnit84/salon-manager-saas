<?php

namespace Tests\Feature\Shops;

use App\Models\User;
use App\Shops\Models\Shop;
use App\Tenancy\Actions\CreateTenantForOwner;
use App\Tenancy\Models\Plan;
use App\Tenancy\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShopsUsersPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
    }

    private function owner(string $mobile = '9000000010', string $biz = 'Biz', string $plan = 'pro'): User
    {
        $u = User::create(['name' => 'Owner', 'mobile' => $mobile]);
        $t = (new CreateTenantForOwner)($u, $biz);
        $t->update(['plan_id' => Plan::where('code', $plan)->value('id')]);

        return $u->fresh();
    }

    public function test_free_plan_blocks_second_shop_at_api(): void
    {
        Sanctum::actingAs($this->owner(plan: 'free'));

        $this->postJson('/api/v1/shops', ['name' => 'One'])->assertCreated();
        $this->postJson('/api/v1/shops', ['name' => 'Two'])->assertStatus(402);
        $this->assertSame(1, Shop::withoutGlobalScopes()->count());
    }

    public function test_slug_is_unique_and_settings_validated_and_merged(): void
    {
        Sanctum::actingAs($this->owner());
        $a = $this->postJson('/api/v1/shops', ['name' => 'Glow'])->json();
        $b = $this->postJson('/api/v1/shops', ['name' => 'Glow'])->json();
        $this->assertNotSame($a['slug'], $b['slug']);

        $this->putJson("/api/v1/shops/{$a['id']}/settings", ['settings' => ['rounding' => 'bogus']])->assertStatus(422);
        $this->putJson("/api/v1/shops/{$a['id']}/settings", ['settings' => ['weekly_off' => [1], 'bill_footer' => 'Hi']])
            ->assertOk()->assertJsonPath('settings.bill_footer', 'Hi')->assertJsonPath('settings.rounding', 'nearest_rupee');
        $this->putJson("/api/v1/shops/{$a['id']}", ['gstin' => 'BAD'])->assertStatus(422);
        $this->putJson("/api/v1/shops/{$a['id']}", ['tax_mode' => 'inclusive'])->assertOk();
    }

    public function test_tenant_b_cannot_see_or_edit_tenant_a_shops(): void
    {
        $a = $this->owner('9000000011', 'A');
        Sanctum::actingAs($a);
        $shopA = $this->postJson('/api/v1/shops', ['name' => 'A shop'])->json();

        $b = $this->owner('9000000012', 'B');
        app(TenantContext::class)->clear();
        Sanctum::actingAs($b);
        $this->getJson('/api/v1/shops')->assertOk()->assertJsonCount(0);
        $this->getJson("/api/v1/shops/{$shopA['id']}")->assertNotFound();
        $this->putJson("/api/v1/shops/{$shopA['id']}", ['name' => 'hacked'])->assertNotFound();
        $this->deleteJson("/api/v1/shops/{$shopA['id']}")->assertNotFound();
    }

    public function test_owner_creates_cashier_with_shop_scoped_role_and_cashier_is_limited(): void
    {
        $owner = $this->owner();
        Sanctum::actingAs($owner);
        $s1 = $this->postJson('/api/v1/shops', ['name' => 'S1'])->json('id');
        $s2 = $this->postJson('/api/v1/shops', ['name' => 'S2'])->json('id');

        $uid = $this->postJson('/api/v1/users', ['name' => 'Ravi', 'mobile' => '9000000020', 'shop_id' => $s1, 'role' => 'cashier'])
            ->assertCreated()->json('id');
        $this->postJson('/api/v1/users', ['name' => 'X', 'mobile' => '9000000021', 'shop_id' => $s1, 'role' => 'shop_owner'])->assertStatus(422);

        $cashier = User::find($uid);
        setPermissionsTeamId($s1);
        $this->assertTrue($cashier->fresh()->hasRole('cashier'));
        setPermissionsTeamId($s2);
        $this->assertFalse($cashier->fresh()->hasRole('cashier'));

        app(TenantContext::class)->clear();
        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/shops')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $s1);
        $this->postJson('/api/v1/shops', ['name' => 'Nope'])->assertForbidden();
        $this->getJson('/api/v1/users')->assertForbidden();
        $this->getJson('/api/v1/shop-context', ['X-Shop-Id' => $s2])->assertNotFound();
    }
}
