<?php

namespace Tests\Feature\Billing;

use App\Catalog\Models\Service;
use App\Models\User;
use App\Staff\Models\Staff;
use App\Staff\Models\StaffServiceCommission;
use App\Staff\Services\CommissionResolver;
use App\Tenancy\Actions\CreateTenantForOwner;
use App\Tenancy\Models\Plan;
use App\Tenancy\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);
    }

    private function owner(string $mobile): User
    {
        $u = User::create(['name' => 'Owner', 'mobile' => $mobile]);
        $t = (new CreateTenantForOwner)($u, 'Biz'.$mobile);
        $t->update(['plan_id' => Plan::where('code', 'pro')->value('id')]);

        return $u->fresh();
    }

    public function test_full_billing_flow_with_commission_snapshot_and_sequential_numbers(): void
    {
        Sanctum::actingAs($this->owner('9000000101'));
        $shop = $this->postJson('/api/v1/shops', ['name' => 'Glow', 'invoice_prefix' => 'GL', 'tax_mode' => 'exclusive'])->json();
        $h = ['X-Shop-Id' => $shop['id']];
        $svc = $this->postJson('/api/v1/services', ['name' => 'Haircut', 'price_paise' => 50000])->assertCreated()->json();
        $staff = $this->postJson('/api/v1/staff', ['name' => 'Ravi', 'commission_type' => 'percent', 'commission_value' => 1000, 'shop_ids' => [$shop['id']]])->assertCreated()->json();
        $cust = $this->postJson('/api/v1/customers', ['name' => 'Anu', 'mobile' => '9876543210'])->assertCreated()->json();

        $payload = fn (string $key) => [
            'customer_id' => $cust['id'], 'idempotency_key' => $key,
            'items' => [['type' => 'service', 'id' => $svc['id'], 'staff_id' => $staff['id']]],
            'payments' => [['method' => 'upi', 'amount' => 59000]],
        ];
        $a = $this->postJson('/api/v1/invoices', $payload('k1'), $h)->assertCreated()->assertJsonPath('total_paise', 59000)->assertJsonPath('status', 'paid');
        $this->assertStringStartsWith('GL/', $a->json('number'));
        $this->assertStringEndsWith('/00001', $a->json('number'));
        $this->assertSame(5000, $a->json('items.0.commission_paise'));

        // Idempotent retry returns the same invoice, no new number
        $this->postJson('/api/v1/invoices', $payload('k1'), $h)->assertOk()->assertJsonPath('id', $a->json('id'));
        $b = $this->postJson('/api/v1/invoices', $payload('k2'), $h)->assertCreated();
        $this->assertStringEndsWith('/00002', $b->json('number'));

        // Changing the rule later does not change the historical invoice
        $this->putJson("/api/v1/staff/{$staff['id']}", ['commission_value' => 5000])->assertOk();
        $this->getJson('/api/v1/invoices/'.$a->json('id'), $h)->assertJsonPath('items.0.commission_paise', 5000);
    }

    public function test_overpayment_rejected_and_due_requires_customer(): void
    {
        Sanctum::actingAs($this->owner('9000000102'));
        $shop = $this->postJson('/api/v1/shops', ['name' => 'S'])->json();
        $svc = $this->postJson('/api/v1/services', ['name' => 'Wash', 'price_paise' => 10000, 'gst_bps' => 0])->json();
        $h = ['X-Shop-Id' => $shop['id']];
        $items = [['type' => 'service', 'id' => $svc['id']]];

        $this->postJson('/api/v1/invoices', ['items' => $items, 'payments' => [['method' => 'cash', 'amount' => 20000]]], $h)->assertStatus(422);
        $this->postJson('/api/v1/invoices', ['items' => $items, 'payments' => [['method' => 'cash', 'amount' => 5000]]], $h)->assertStatus(422)->assertJsonValidationErrors('customer_id');
    }

    public function test_cross_tenant_catalog_and_cashier_commission_hidden(): void
    {
        $a = $this->owner('9000000103');
        Sanctum::actingAs($a);
        $shop = $this->postJson('/api/v1/shops', ['name' => 'S'])->json();
        $svc = $this->postJson('/api/v1/services', ['name' => 'Secret', 'price_paise' => 100, 'commission_type' => 'percent', 'commission_value' => 500])->json();

        Sanctum::actingAs($this->owner('9000000104'));
        $this->getJson('/api/v1/services')->assertOk()->assertJsonCount(0);
        $this->putJson("/api/v1/services/{$svc['id']}", ['name' => 'Hacked'])->assertNotFound();

        // non-owner member of tenant A: cannot write catalog, sees no commission fields
        $cashier = User::create(['name' => 'C', 'mobile' => '9000000105', 'tenant_id' => $a->tenant_id]);
        \DB::table('shop_user')->insert(['tenant_id' => $a->tenant_id, 'shop_id' => $shop['id'], 'user_id' => $cashier->id]);
        $h = ['X-Shop-Id' => $shop['id']];
        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/services', ['name' => 'X', 'price_paise' => 1], $h)->assertForbidden();
        $this->getJson('/api/v1/services', $h)->assertOk()->assertJsonMissingPath('0.commission_value');
    }

    public function test_commission_precedence(): void
    {
        $u = $this->owner('9000000106');
        app(TenantContext::class)->set($u->tenant_id);
        $staff = Staff::create(['name' => 'S', 'commission_type' => 'percent', 'commission_value' => 1000]);
        $plain = Service::create(['name' => 'A', 'price_paise' => 100]);
        $withDefault = Service::create(['name' => 'B', 'price_paise' => 100, 'commission_type' => 'fixed', 'commission_value' => 2500]);
        $r = new CommissionResolver;

        $this->assertSame('staff', $r->rule($staff, $plain)['source']);
        $this->assertSame('service', $r->rule($staff, $withDefault)['source']);
        StaffServiceCommission::create(['staff_id' => $staff->id, 'service_id' => $withDefault->id, 'commission_type' => 'percent', 'commission_value' => 2000]);
        $rule = $r->rule($staff, $withDefault);
        $this->assertSame('staff_service', $rule['source']);
        $this->assertSame(2000, $r->amount($rule, \App\Shared\Support\Money::paise(10000))->paise);
    }
}
