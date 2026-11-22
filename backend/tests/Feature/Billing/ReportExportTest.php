<?php

namespace Tests\Feature\Billing;

use App\Reports\Services\DailySummaryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MakesTenants;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use MakesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTenants();
    }

    private function bill(array $h, int $svc, ?int $cust = null): void
    {
        $this->postJson('/api/v1/invoices', ['items' => [['type' => 'service', 'id' => $svc]], 'pay_in_full' => 'cash', 'customer_id' => $cust], $h)->assertCreated();
    }

    public function test_every_report_type_returns_a_table_scoped_to_the_tenant(): void
    {
        $a = $this->owner('9833000001');
        Sanctum::actingAs($a);
        $shop = $this->postJson('/api/v1/shops', ['name' => 'S'])->json();
        $svc = $this->postJson('/api/v1/services', ['name' => 'Cut', 'price_paise' => 10000, 'gst_bps' => 1800])->json();
        $this->postJson('/api/v1/expenses', ['shop_id' => $shop['id'], 'category' => 'Rent', 'amount_paise' => 3000, 'spent_on' => now()->toDateString()])->assertCreated();
        $this->bill(['X-Shop-Id' => $shop['id']], $svc['id']);

        foreach (\App\Reports\Services\ReportTables::TYPES as $type) {
            $this->getJson("/api/v1/reports/table/{$type}")->assertOk()->assertJsonStructure(['title', 'columns', 'rows']);
        }
        $pnl = $this->getJson('/api/v1/reports/table/pnl')->json();
        $this->assertSame(10000, $pnl['rows'][0][1]);                 // net sales excl. GST
        $this->assertSame(10000 - 3000, $pnl['summary']['Net profit']);
        $gst = $this->getJson('/api/v1/reports/table/gst')->json('rows.0');
        $this->assertSame([10000, 900, 900, 11800], [$gst[2], $gst[3], $gst[4], $gst[5]]);

        // a second tenant has bills too, and must see none of tenant A's rows in any report
        $b = $this->owner('9833000002');
        Sanctum::actingAs($b);
        $shopB = $this->postJson('/api/v1/shops', ['name' => 'B'])->json();
        $svcB = $this->postJson('/api/v1/services', ['name' => 'Other', 'price_paise' => 99900, 'gst_bps' => 0])->json();
        $this->bill(['X-Shop-Id' => $shopB['id']], $svcB['id']);
        foreach (['sales', 'services', 'gst', 'discounts', 'customers'] as $type) {
            $rows = json_encode($this->getJson("/api/v1/reports/table/{$type}")->json('rows'));
            $this->assertStringNotContainsString('Cut', $rows);
        }
        $this->assertCount(1, $this->getJson('/api/v1/reports/table/gst')->json('rows'));
        $this->getJson('/api/v1/reports/table/nope')->assertNotFound();
    }

    public function test_exports_csv_xlsx_pdf_and_owner_only(): void
    {
        $a = $this->owner('9833000003');
        Sanctum::actingAs($a);
        $shop = $this->postJson('/api/v1/shops', ['name' => 'S'])->json();
        $svc = $this->postJson('/api/v1/services', ['name' => 'Cut', 'price_paise' => 12345, 'gst_bps' => 0])->json();
        $this->bill(['X-Shop-Id' => $shop['id']], $svc['id']);

        $csv = $this->get('/api/v1/reports/services/export?format=csv')->assertOk();
        $this->assertStringContainsString('Cut', $csv->streamedContent());
        $this->assertStringContainsString('123.45', $csv->streamedContent());
        $this->get('/api/v1/reports/services/export?format=xlsx')->assertOk()->assertHeader('content-disposition');
        $this->get('/api/v1/reports/services/export?format=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/api/v1/reports/services/export?format=exe')->assertStatus(422);

        $cashier = $this->member($a, '9833000004', 'cashier', $shop['id']);
        Sanctum::actingAs($cashier);
        $this->get('/api/v1/reports/services/export?format=csv', ['X-Shop-Id' => $shop['id']])->assertForbidden();
    }

    public function test_daily_summaries_match_invoices_and_rebuild_is_idempotent(): void
    {
        $a = $this->owner('9833000005');
        Sanctum::actingAs($a);
        $shop = $this->postJson('/api/v1/shops', ['name' => 'S'])->json();
        $svc = $this->postJson('/api/v1/services', ['name' => 'Cut', 'price_paise' => 10000, 'gst_bps' => 0])->json();
        $cust = $this->postJson('/api/v1/customers', ['name' => 'C', 'mobile' => '9811111111'])->json();
        $this->bill(['X-Shop-Id' => $shop['id']], $svc['id'], $cust['id']);
        $this->bill(['X-Shop-Id' => $shop['id']], $svc['id'], $cust['id']);

        $b = new DailySummaryBuilder;
        $day = CarbonImmutable::now('Asia/Kolkata');
        $this->assertSame(1, $b->build($a->tenant_id, $day, $day));
        $b->build($a->tenant_id, $day, $day);
        $row = \DB::table('daily_shop_summary')->where('tenant_id', $a->tenant_id)->first();
        $this->assertSame(1, \DB::table('daily_shop_summary')->count());
        $this->assertSame([20000, 2, 20000, 1, 0], [(int) $row->net, (int) $row->bills, (int) $row->cash, (int) $row->customers_new, (int) $row->customers_returning]);
    }
}
