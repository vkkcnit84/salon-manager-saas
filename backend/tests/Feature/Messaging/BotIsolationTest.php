<?php

namespace Tests\Feature\Messaging;

use App\Bot\Services\BotRouter;
use App\Customers\Models\Customer;
use App\Messaging\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MakesTenants;
use Tests\TestCase;

class BotIsolationTest extends TestCase
{
    use MakesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTenants();
        Cache::flush();
    }

    private function salon(string $mobile, string $shopName = 'Glow'): array
    {
        $o = $this->owner($mobile, credits: 100);
        Sanctum::actingAs($o);
        $shop = $this->postJson('/api/v1/shops', ['name' => $shopName])->json();
        $svc = $this->postJson('/api/v1/services', ['name' => 'Cut', 'price_paise' => 50000, 'gst_bps' => 0])->json();
        $this->postJson('/api/v1/invoices', ['items' => [['type' => 'service', 'id' => $svc['id']]], 'pay_in_full' => 'cash'], ['X-Shop-Id' => $shop['id']])->assertCreated();

        return [$o, $shop];
    }

    public function test_simulator_cannot_impersonate_another_tenants_owner(): void
    {
        [$a] = $this->salon('9840000001');
        [$b] = $this->salon('9840000002');
        $tplA = $this->inTenant($a, fn () => \App\Messaging\Models\MessageTemplate::create(['channel' => 'whatsapp', 'name' => 'x', 'category' => 'marketing', 'body' => 'Hi {{1}}', 'status' => 'approved']));
        $draftA = $this->inTenant($a, fn () => Campaign::create(['name' => 'A draft', 'template_id' => $tplA->id, 'channel' => 'whatsapp', 'audience' => [], 'status' => 'draft']));

        Sanctum::actingAs($b); // tenant B's owner pretends to be tenant A's owner
        $r = $this->postJson('/api/v1/bot/simulate', ['from' => '9840000001', 'text' => 'dues'])->assertOk()->json('replies.0.text');
        $this->assertStringNotContainsString('Pending dues', $r);          // never answered with A's data
        $this->postJson('/api/v1/bot/simulate', ['from' => '9840000001', 'button' => "winback:{$draftA->id}:send"])->assertOk();
        $this->assertSame('draft', Campaign::withoutGlobalScopes()->find($draftA->id)->status); // A's draft untouched
        $this->assertSame(0, \DB::table('audit_logs')->where('tenant_id', $a->tenant_id)->where('action', 'like', 'bot.%')->count());

        $this->postJson('/api/v1/bot/simulate', ['from' => '9840000002', 'text' => 'today'])->assertOk()->assertSee('Today'); // own mobile still works
    }

    public function test_checkin_codes_are_globally_unique_and_land_in_the_right_tenant(): void
    {
        [$a, $shopA] = $this->salon('9840000011', 'Glow');
        [$b, $shopB] = $this->salon('9840000012', 'Glow');   // identically named shop in another tenant
        $this->assertNotSame($shopA['code'], $shopB['code']);

        $bot = app(BotRouter::class);
        $bot->handle('9899900001', 'CHECKIN-'.$shopB['code']);
        $bot->handle('9899900001', 'Meena');
        $bot->handle('9899900001', '', 'consent:yes');

        $c = Customer::withoutGlobalScopes()->where('mobile', '9899900001')->get();
        $this->assertCount(1, $c);
        $this->assertSame($b->tenant_id, (int) $c[0]->tenant_id);   // B's customer, not A's
        $this->assertSame(0, \DB::table('waiting_queue')->where('tenant_id', $a->tenant_id)->count());
        $this->assertSame(1, \DB::table('waiting_queue')->where('tenant_id', $b->tenant_id)->where('shop_id', $shopB['id'])->count());
    }

    public function test_dashboard_trend_uses_live_data_even_when_summary_rows_are_stale(): void
    {
        [$o, $shop] = $this->salon('9840000021');
        $bill = \DB::table('invoices')->where('tenant_id', $o->tenant_id)->first();
        \DB::table('invoices')->where('id', $bill->id)->update(['created_at' => now()->subDays(3)]);
        for ($i = 1; $i <= 25; $i++) { // 25 stale zero rows would previously hide real sales behind a "built" summary
            \DB::table('daily_shop_summary')->insert(['tenant_id' => $o->tenant_id, 'shop_id' => $shop['id'], 'date' => now('Asia/Kolkata')->subDays($i + 5)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        }
        Sanctum::actingAs($o);
        $d = $this->getJson('/api/v1/reports/dashboard')->assertOk()->json();
        $this->assertSame(50000, array_sum(array_column($d['last30']['daily'], 'sales')));
        $this->assertSame(50000, $d['last30']['totals']['sales']);
    }
}
