<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Tenancy\Actions\CreateTenantForOwner;
use App\Tenancy\Models\Plan;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsTest extends TestCase
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
        (new CreateTenantForOwner)($u, 'B'.$mobile)->update(['plan_id' => Plan::where('code', 'pro')->value('id')]);

        return $u->fresh();
    }

    public function test_sales_staff_dues_and_dashboard_reflect_bills_and_are_tenant_scoped(): void
    {
        Sanctum::actingAs($this->owner('9000000201'));
        $shop = $this->postJson('/api/v1/shops', ['name' => 'S'])->json();
        $h = ['X-Shop-Id' => $shop['id']];
        $svc = $this->postJson('/api/v1/services', ['name' => 'Cut', 'price_paise' => 10000, 'gst_bps' => 0])->json();
        $staff = $this->postJson('/api/v1/staff', ['name' => 'Ravi', 'commission_value' => 1000, 'shop_ids' => [$shop['id']]])->json();
        $cust = $this->postJson('/api/v1/customers', ['name' => 'A', 'mobile' => '9876500001'])->json();
        $items = [['type' => 'service', 'id' => $svc['id'], 'staff_id' => $staff['id']]];

        $this->postJson('/api/v1/invoices', ['items' => $items, 'pay_in_full' => 'upi'], $h)->assertCreated();
        $this->postJson('/api/v1/invoices', ['items' => $items, 'customer_id' => $cust['id'], 'payments' => [['method' => 'cash', 'amount' => 4000]]], $h)->assertCreated()->assertJsonPath('status', 'due');

        $sales = $this->getJson('/api/v1/reports/sales', $h)->assertOk();
        $sales->assertJsonPath('totals.sales', 20000)->assertJsonPath('totals.bills', 2)->assertJsonPath('totals.due', 6000)->assertJsonPath('totals.avg_bill', 10000);
        $this->assertCount(30, $sales->json('daily'));
        $this->assertSame('Cut', $sales->json('top_services.0.name'));
        $this->assertEqualsCanonicalizing(['upi', 'cash'], array_column($sales->json('payment_methods'), 'method'));

        $this->getJson('/api/v1/reports/staff', $h)->assertJsonPath('0.revenue', 20000)->assertJsonPath('0.commission', 2000);
        $this->getJson('/api/v1/reports/dues', $h)->assertJsonPath('0.due', 6000)->assertJsonPath('0.customer', 'A');
        $this->getJson('/api/v1/reports/dashboard', $h)->assertOk()->assertJsonPath('today.sales', 20000)->assertJsonPath('dues_total', 6000);

        Sanctum::actingAs($this->owner('9000000202'));
        $this->getJson('/api/v1/reports/sales')->assertOk()->assertJsonPath('totals.sales', 0); // other tenant sees nothing
        $this->getJson('/api/v1/reports/sales?from=2026-12-31&to=2026-01-01')->assertStatus(422);
    }

    public function test_non_owner_cannot_read_reports(): void
    {
        $o = $this->owner('9000000203');
        Sanctum::actingAs($o);
        $shop = $this->postJson('/api/v1/shops', ['name' => 'S'])->json();
        $cashier = User::create(['name' => 'C', 'mobile' => '9000000204', 'tenant_id' => $o->tenant_id]);
        \DB::table('shop_user')->insert(['tenant_id' => $o->tenant_id, 'shop_id' => $shop['id'], 'user_id' => $cashier->id]);
        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/reports/dashboard', ['X-Shop-Id' => $shop['id']])->assertForbidden();
    }
}
