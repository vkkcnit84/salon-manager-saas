<?php

namespace App\Console\Commands;

use App\Reports\Services\DailySummaryBuilder;
use App\Reports\Services\ReportService;
use App\Tenancy\Models\Plan;
use App\Tenancy\Models\Tenant;
use App\Tenancy\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Seeds N invoices into a throwaway tenant and times the dashboard against the <1.5 s target. --cleanup removes it. */
class PerfDashboard extends Command
{
    protected $signature = 'perf:dashboard {--seed=100000} {--cleanup}';

    protected $description = 'Load-test the dashboard with a large synthetic tenant';

    public function handle(ReportService $reports, DailySummaryBuilder $builder): int
    {
        $name = 'Perf Test Salon';
        if ($this->option('cleanup')) {
            $t = Tenant::where('business_name', $name)->first();
            if ($t) {
                foreach (['payments', 'invoice_items', 'invoices', 'daily_staff_summary', 'daily_shop_summary', 'shop_counters', 'staff', 'services', 'shops'] as $tbl) {
                    DB::table($tbl)->where('tenant_id', $t->id)->delete();
                }
                $t->forceDelete();
            }
            $this->info('Cleaned up.');

            return 0;
        }

        $tenant = Tenant::where('business_name', $name)->first() ?? Tenant::create(['business_name' => $name, 'plan_id' => Plan::where('code', 'pro')->value('id'), 'status' => 'trial', 'message_credits' => 1000]);
        $tid = $tenant->id;
        $shopId = DB::table('shops')->where('tenant_id', $tid)->value('id') ?? DB::table('shops')->insertGetId(['tenant_id' => $tid, 'name' => 'Perf Shop', 'slug' => 'perf-shop', 'created_at' => now(), 'updated_at' => now()]);
        $svc = DB::table('services')->where('tenant_id', $tid)->value('id') ?? DB::table('services')->insertGetId(['tenant_id' => $tid, 'name' => 'Haircut', 'price_paise' => 50000, 'created_at' => now(), 'updated_at' => now()]);
        $staff = DB::table('staff')->where('tenant_id', $tid)->value('id') ?? DB::table('staff')->insertGetId(['tenant_id' => $tid, 'name' => 'Perf Staff', 'created_at' => now(), 'updated_at' => now()]);

        $have = DB::table('invoices')->where('tenant_id', $tid)->count();
        $want = (int) $this->option('seed');
        if ($have < $want) {
            $this->info('Seeding '.($want - $have).' invoices…');
            $seq = (int) DB::table('invoices')->where('tenant_id', $tid)->max('seq');
            $start = microtime(true);
            for ($i = $have; $i < $want; $i += 1000) {
                $inv = [];
                for ($j = 0; $j < min(1000, $want - $i); $j++) {
                    $at = now()->subSeconds(mt_rand(0, 365 * 86400));
                    $total = mt_rand(2, 30) * 10000;
                    $inv[] = ['tenant_id' => $tid, 'shop_id' => $shopId, 'financial_year' => '2026-27', 'seq' => ++$seq, 'number' => "PF/{$seq}", 'status' => 'paid', 'tax_mode' => 'exclusive', 'subtotal_paise' => $total, 'discount_paise' => 0, 'tax_paise' => intdiv($total * 18, 118), 'tip_paise' => 0, 'rounding_paise' => 0, 'total_paise' => $total, 'paid_paise' => $total, 'due_paise' => 0, 'created_at' => $at, 'updated_at' => $at];
                }
                DB::table('invoices')->insert($inv);
                $first = DB::table('invoices')->where('tenant_id', $tid)->where('seq', '>', $seq - count($inv))->orderBy('seq')->pluck('id', 'seq');
                $items = $pays = [];
                foreach ($inv as $row) {
                    $id = $first[$row['seq']];
                    $items[] = ['tenant_id' => $tid, 'invoice_id' => $id, 'item_type' => 'service', 'item_id' => $svc, 'name' => 'Haircut', 'qty' => 1, 'unit_price_paise' => $row['total_paise'], 'discount_paise' => 0, 'gst_bps' => 1800, 'taxable_paise' => $row['total_paise'], 'tax_paise' => 0, 'total_paise' => $row['total_paise'], 'staff_id' => $staff, 'commission_paise' => intdiv($row['total_paise'], 10)];
                    $pays[] = ['tenant_id' => $tid, 'invoice_id' => $id, 'method' => ['cash', 'upi', 'card'][mt_rand(0, 2)], 'amount_paise' => $row['total_paise'], 'created_at' => $row['created_at'], 'updated_at' => $row['created_at']];
                }
                DB::table('invoice_items')->insert($items);
                DB::table('payments')->insert($pays);
            }
            $this->info(sprintf('Seeded in %.1fs', microtime(true) - $start));
            $this->info('Built '.$builder->build($tid, CarbonImmutable::now()->subDays(370), CarbonImmutable::now()).' summary rows');
        }

        app(TenantContext::class)->set($tid);
        $times = [];
        foreach (range(1, 7) as $_) {
            $t = microtime(true);
            $reports->dashboard(null);
            $times[] = (microtime(true) - $t) * 1000;
        }
        sort($times);
        $this->table(['invoices', 'min ms', 'median ms', 'max ms', 'target'], [[DB::table('invoices')->where('tenant_id', $tid)->count(), round($times[0]), round($times[3]), round($times[6]), '< 1500']]);

        return $times[3] < 1500 ? 0 : 1;
    }
}
