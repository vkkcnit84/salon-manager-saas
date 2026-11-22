<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Shops\Models\Shop;
use App\Tenancy\Models\Tenant;
use App\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{tenant: Tenant, owner: User, shop: Shop} */
    private function makeTenant(string $name): array
    {
        $tenant = Tenant::create(['business_name' => $name]);
        $owner = User::create(['tenant_id' => $tenant->id, 'name' => "$name owner", 'mobile' => fake()->unique()->numerify('9#########'), 'is_owner' => true]);
        $shop = $this->makeShop($tenant, "$name shop", 'main');

        return compact('tenant', 'owner', 'shop');
    }

    private function makeShop(Tenant $tenant, string $name, string $slug): Shop
    {
        $shop = new Shop(['name' => $name, 'slug' => $slug]);
        $shop->tenant_id = $tenant->id;
        $shop->save();

        return $shop;
    }

    public function test_scope_only_returns_current_tenant_rows(): void
    {
        $a = $this->makeTenant('A');
        $b = $this->makeTenant('B');

        app(TenantContext::class)->set($a['tenant']->id);

        $this->assertSame([$a['shop']->id], Shop::pluck('id')->all());
        $this->assertNull(Shop::find($b['shop']->id));
    }

    public function test_no_tenant_context_fails_closed(): void
    {
        $this->makeTenant('A');
        app(TenantContext::class)->clear();

        $this->assertCount(0, Shop::all());
        $this->expectException(\LogicException::class);
        Shop::create(['name' => 'x', 'slug' => 'x']);
    }

    public function test_cross_tenant_write_is_blocked(): void
    {
        $a = $this->makeTenant('A');
        $b = $this->makeTenant('B');
        app(TenantContext::class)->set($a['tenant']->id);

        $this->expectException(\LogicException::class);
        $shop = new Shop(['name' => 'evil', 'slug' => 'evil']);
        $shop->tenant_id = $b['tenant']->id;
        $shop->save();
    }

    public function test_owner_can_use_own_shop_header(): void
    {
        $a = $this->makeTenant('A');
        Sanctum::actingAs($a['owner']);

        $this->getJson('/api/v1/shop-context', ['X-Shop-Id' => $a['shop']->id])
            ->assertOk()->assertJson(['shop_id' => $a['shop']->id]);
    }

    public function test_other_tenant_and_unknown_shop_ids_return_identical_404(): void
    {
        $a = $this->makeTenant('A');
        $b = $this->makeTenant('B');
        Sanctum::actingAs($a['owner']);

        $other = $this->getJson('/api/v1/shop-context', ['X-Shop-Id' => $b['shop']->id]);
        $unknown = $this->getJson('/api/v1/shop-context', ['X-Shop-Id' => 999999]);

        $other->assertNotFound();
        $unknown->assertNotFound();
        $this->assertSame($unknown->json('message'), $other->json('message'));
    }

    public function test_non_owner_needs_shop_assignment(): void
    {
        $a = $this->makeTenant('A');
        $second = $this->makeShop($a['tenant'], 'Second', 'second');
        $cashier = User::create(['tenant_id' => $a['tenant']->id, 'name' => 'C', 'mobile' => '9000000001']);
        \DB::table('shop_user')->insert(['tenant_id' => $a['tenant']->id, 'shop_id' => $a['shop']->id, 'user_id' => $cashier->id]);
        Sanctum::actingAs($cashier);

        $this->getJson('/api/v1/shop-context', ['X-Shop-Id' => $a['shop']->id])->assertOk();
        $this->getJson('/api/v1/shop-context', ['X-Shop-Id' => $second->id])->assertNotFound();
    }

    public function test_missing_header_is_rejected(): void
    {
        $a = $this->makeTenant('A');
        Sanctum::actingAs($a['owner']);

        $this->getJson('/api/v1/shop-context')->assertStatus(422);
    }

    public function test_health_endpoint(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJson(['status' => 'ok']);
    }
}
