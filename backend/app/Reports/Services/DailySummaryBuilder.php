<?php

namespace App\Reports\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Pre-aggregates invoices into per-shop and per-staff daily rows (shop-local days). Idempotent: rows are replaced. */
class DailySummaryBuilder
{
    /** Processes the range in 14-day windows so memory stays flat however many invoices exist. */
    public function build(int $tenantId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $tz = ReportService::TZ;
        $n = 0;
        for ($a = $from->setTimezone($tz)->startOfDay(); $a->lte($to->setTimezone($tz)); $a = $a->addDays(14)) {
            $n += $this->buildWindow($tenantId, $a, $a->addDays(13)->min($to->setTimezone($tz)));
        }

        return $n;
    }

    private function buildWindow(int $tenantId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $tz = ReportService::TZ;
        $start = $from->setTimezone($tz)->startOfDay();
        $end = $to->setTimezone($tz)->endOfDay();
        $inv = DB::table('invoices')->where('tenant_id', $tenantId)->where('status', '!=', 'void')->whereBetween('created_at', [$start->utc(), $end->utc()])
            ->get(['id', 'shop_id', 'customer_id', 'created_at', 'subtotal_paise', 'discount_paise', 'tax_paise', 'total_paise']);
        if ($inv->isEmpty()) {
            return 0;
        }
        $day = fn ($i) => CarbonImmutable::parse($i->created_at)->setTimezone($tz)->toDateString();
        $ids = $inv->pluck('id');
        $pay = DB::table('payments')->whereIn('invoice_id', $ids)->get(['invoice_id', 'method', 'amount_paise'])->groupBy('invoice_id');
        $items = DB::table('invoice_items')->whereIn('invoice_id', $ids)->get(['invoice_id', 'staff_id', 'qty', 'total_paise', 'commission_paise'])->groupBy('invoice_id');
        $firstSeen = DB::table('invoices')->where('tenant_id', $tenantId)->whereNotNull('customer_id')->where('status', '!=', 'void')->groupBy('customer_id')->selectRaw('customer_id, min(created_at) f')->pluck('f', 'customer_id');

        $shops = [];
        $staff = [];
        foreach ($inv as $i) {
            $d = $day($i);
            $s = &$shops[$i->shop_id][$d];
            $s ??= ['gross' => 0, 'discount' => 0, 'tax' => 0, 'net' => 0, 'bills' => 0, 'new' => [], 'ret' => [], 'cash' => 0, 'upi' => 0, 'card' => 0, 'commission' => 0];
            $s['gross'] += $i->subtotal_paise;
            $s['discount'] += $i->discount_paise;
            $s['tax'] += $i->tax_paise;
            $s['net'] += $i->total_paise;
            $s['bills']++;
            foreach ($pay[$i->id] ?? [] as $p) {
                $s[$p->method] += $p->amount_paise;
            }
            if ($i->customer_id) {
                $isNew = $firstSeen[$i->customer_id] && $day((object) ['created_at' => $firstSeen[$i->customer_id]]) === $d;
                $s[$isNew ? 'new' : 'ret'][$i->customer_id] = true;
            }
            foreach ($items[$i->id] ?? [] as $it) {
                $s['commission'] += $it->commission_paise;
                if ($it->staff_id) {
                    $t = &$staff[$it->staff_id][$i->shop_id][$d];
                    $t ??= ['n' => 0, 'rev' => 0, 'com' => 0];
                    $t['n'] += $it->qty;
                    $t['rev'] += $it->total_paise;
                    $t['com'] += $it->commission_paise;
                    unset($t);
                }
            }
            unset($s);
        }

        $expenses = DB::table('expenses')->where('tenant_id', $tenantId)->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])->get(['shop_id', 'spent_on', 'amount_paise'])->groupBy(fn ($e) => $e->shop_id.'|'.substr((string) $e->spent_on, 0, 10));

        $n = 0;
        DB::transaction(function () use ($tenantId, $start, $end, $shops, $staff, $expenses, &$n) {
            DB::table('daily_shop_summary')->where('tenant_id', $tenantId)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->delete();
            DB::table('daily_staff_summary')->where('tenant_id', $tenantId)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->delete();
            foreach ($shops as $shopId => $days) {
                foreach ($days as $d => $s) {
                    DB::table('daily_shop_summary')->insert([
                        'tenant_id' => $tenantId, 'shop_id' => $shopId, 'date' => $d, 'gross' => $s['gross'], 'discount' => $s['discount'], 'tax' => $s['tax'], 'net' => $s['net'], 'bills' => $s['bills'],
                        'customers_new' => count($s['new']), 'customers_returning' => count($s['ret']), 'cash' => $s['cash'], 'upi' => $s['upi'], 'card' => $s['card'], 'due_collected' => 0,
                        'expenses' => (int) ($expenses[$shopId.'|'.$d] ?? collect())->sum('amount_paise'), 'commission' => $s['commission'], 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $n++;
                }
            }
            foreach ($staff as $staffId => $byShop) {
                foreach ($byShop as $shopId => $days) {
                    foreach ($days as $d => $t) {
                        DB::table('daily_staff_summary')->insert(['tenant_id' => $tenantId, 'staff_id' => $staffId, 'shop_id' => $shopId, 'date' => $d, 'services_count' => $t['n'], 'revenue' => $t['rev'], 'commission' => $t['com'], 'tips' => 0, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        });

        return $n;
    }
}
