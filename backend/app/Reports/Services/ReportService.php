<?php

namespace App\Reports\Services;

use App\Billing\Models\Invoice;
use App\Billing\Models\InvoiceItem;
use App\Billing\Models\Payment;
use App\Staff\Models\Staff;
use Carbon\CarbonImmutable;

/**
 * Aggregates in PHP over a bounded date range so the same code runs on MySQL and SQLite.
 * All money is integer paise; days are bucketed in the business timezone, not UTC.
 */
class ReportService
{
    public const TZ = 'Asia/Kolkata';

    public function range(?string $from, ?string $to): array
    {
        $end = $to ? CarbonImmutable::parse($to, self::TZ)->endOfDay() : CarbonImmutable::now(self::TZ)->endOfDay();
        $start = $from ? CarbonImmutable::parse($from, self::TZ)->startOfDay() : $end->subDays(29)->startOfDay();
        abort_if($start->gt($end) || $start->diffInDays($end) > 400, 422, 'Invalid date range.');

        return [$start, $end];
    }

    private function invoices(?int $shopId, CarbonImmutable $start, CarbonImmutable $end)
    {
        return Invoice::query()
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->where('status', '!=', 'void')
            ->whereBetween('created_at', [$start->utc(), $end->utc()]);
    }

    private function day($ts): string
    {
        return CarbonImmutable::parse($ts)->setTimezone(self::TZ)->toDateString();
    }

    private function dayKeys(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $keys = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $keys[$d->toDateString()] = ['date' => $d->toDateString(), 'sales' => 0, 'bills' => 0];
        }

        return $keys;
    }

    /** Day bucket in shop time (IST has no DST, so a fixed offset is exact). */
    private function dayExpr(string $col = 'i.created_at'): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite'
            ? "date({$col}, '+330 minutes')"
            : "DATE(CONVERT_TZ({$col}, '+00:00', '+05:30'))";
    }

    private function base(?int $shopId, CarbonImmutable $start, CarbonImmutable $end)
    {
        return \Illuminate\Support\Facades\DB::table('invoices as i')
            ->where('i.tenant_id', app(\App\Tenancy\Support\TenantContext::class)->tenantId())
            ->where('i.status', '!=', 'void')
            ->when($shopId, fn ($q) => $q->where('i.shop_id', $shopId))
            ->whereBetween('i.created_at', [$start->utc()->toDateTimeString(), $end->utc()->toDateTimeString()]);
    }

    /** All aggregation happens in SQL so cost stays flat as invoice volume grows. */
    public function sales(?int $shopId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $t = $this->base($shopId, $start, $end)->selectRaw('count(*) bills, coalesce(sum(i.total_paise),0) sales, coalesce(sum(i.tax_paise),0) tax, coalesce(sum(i.discount_paise),0) discount, coalesce(sum(i.tip_paise),0) tips, coalesce(sum(i.paid_paise),0) collected, coalesce(sum(i.due_paise),0) due, count(distinct i.customer_id) customers')->first();

        $days = $this->dayKeys($start, $end);
        foreach ($this->base($shopId, $start, $end)->selectRaw($this->dayExpr().' d, count(*) b, sum(i.total_paise) s')->groupBy('d')->get() as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['sales'] = (int) $r->s;
                $days[$r->d]['bills'] = (int) $r->b;
            }
        }

        $top = fn (string $type) => $this->base($shopId, $start, $end)->join('invoice_items as it', 'it.invoice_id', '=', 'i.id')->where('it.item_type', $type)
            ->selectRaw('it.item_id, max(it.name) name, sum(it.qty) qty, sum(it.total_paise) revenue')->groupBy('it.item_id')->orderByDesc('revenue')->limit(10)->get()
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => (int) $r->revenue])->all();

        $methods = $this->base($shopId, $start, $end)->join('payments as p', 'p.invoice_id', '=', 'i.id')
            ->selectRaw('p.method, sum(p.amount_paise) amount, count(*) n')->groupBy('p.method')->get()
            ->map(fn ($r) => ['method' => $r->method, 'amount' => (int) $r->amount, 'count' => (int) $r->n])->all();

        $bills = (int) $t->bills;
        $sales = (int) $t->sales;

        return [
            'from' => $start->toDateString(), 'to' => $end->toDateString(),
            'totals' => [
                'sales' => $sales, 'bills' => $bills, 'avg_bill' => $bills ? intdiv($sales, $bills) : 0, 'tax' => (int) $t->tax, 'discount' => (int) $t->discount, 'tips' => (int) $t->tips,
                'collected' => (int) $t->collected, 'due' => (int) $t->due, 'customers' => (int) $t->customers,
            ],
            'daily' => array_values($days),
            'top_services' => $top('service'),
            'top_products' => $top('product'),
            'payment_methods' => $methods,
        ];
    }

    public function staff(?int $shopId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = $this->base($shopId, $start, $end)->join('invoice_items as it', 'it.invoice_id', '=', 'i.id')->whereNotNull('it.staff_id')
            ->selectRaw('it.staff_id, sum(it.qty) services, sum(it.total_paise) revenue, sum(it.commission_paise) commission')->groupBy('it.staff_id')->orderByDesc('revenue')->get();
        $names = Staff::withTrashed()->whereIn('id', $rows->pluck('staff_id'))->pluck('name', 'id');

        return $rows->map(fn ($r) => ['staff_id' => (int) $r->staff_id, 'name' => $names[$r->staff_id] ?? 'Unknown', 'services' => (int) $r->services, 'revenue' => (int) $r->revenue, 'commission' => (int) $r->commission])->all();
    }

    public function dues(?int $shopId): array
    {
        return Invoice::with('customer:id,name,mobile')->where('due_paise', '>', 0)->where('status', 'due')
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))->latest('id')->limit(200)->get()
            ->map(fn ($i) => ['number' => $i->number, 'date' => $this->day($i->created_at), 'customer' => $i->customer?->name ?? 'Walk-in', 'mobile' => $i->customer?->mobile, 'total' => $i->total_paise, 'due' => $i->due_paise])->all();
    }

    public function dashboard(?int $shopId): array
    {
        $now = CarbonImmutable::now(self::TZ);
        $today = $this->sales($shopId, $now->startOfDay(), $now->endOfDay())['totals'];
        $yesterday = $this->sales($shopId, $now->subDay()->startOfDay(), $now->subDay()->endOfDay())['totals'];
        $month = $this->sales($shopId, $now->startOfMonth(), $now->endOfDay());
        $last30 = $this->sales($shopId, $now->subDays(29)->startOfDay(), $now->endOfDay());
        $prev30 = $this->sales($shopId, $now->subDays(59)->startOfDay(), $now->subDays(30)->endOfDay())['totals'];
        $recent = $this->invoices($shopId, $now->subDays(30)->startOfDay(), $now->endOfDay())->with('customer:id,name')->latest('id')->limit(8)->get()
            ->map(fn ($i) => ['number' => $i->number, 'customer' => $i->customer?->name ?? 'Walk-in', 'total' => $i->total_paise, 'status' => $i->status, 'at' => $i->created_at->toIso8601String()]);

        return [
            'shop_comparison' => $shopId ? [] : $this->shopComparison($now->subDays(29)->startOfDay(), $now),
            'alerts' => $this->alerts($shopId, $now),
            'today' => $today, 'yesterday' => $yesterday,
            'month' => $month['totals'],
            'last30' => ['totals' => $last30['totals'], 'previous_sales' => $prev30['sales'], 'daily' => $last30['daily']],
            'top_services' => array_slice($last30['top_services'], 0, 5),
            'payment_methods' => $last30['payment_methods'],
            'staff' => array_slice($this->staff($shopId, $now->subDays(29)->startOfDay(), $now->endOfDay()), 0, 5),
            'recent' => $recent,
            'dues_total' => (int) Invoice::where('status', 'due')->when($shopId, fn ($q) => $q->where('shop_id', $shopId))->sum('due_paise'),
        ];
    }

    /** Live SQL (never the summary tables): an unbuilt day must not read as "no sales". */
    private function shopComparison(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return $this->base(null, $start, $end)->join('shops as s', 's.id', '=', 'i.shop_id')->selectRaw('s.id, s.name, count(*) bills, sum(i.total_paise) sales')
            ->groupBy('s.id', 's.name')->orderByDesc('sales')->get()
            ->map(fn ($r) => ['shop_id' => (int) $r->id, 'name' => $r->name, 'sales' => (int) $r->sales, 'bills' => (int) $r->bills])->all();
    }

    private function alerts(?int $shopId, CarbonImmutable $now): array
    {
        $alerts = [];
        $credits = (int) \App\Tenancy\Models\Tenant::whereKey(app(\App\Tenancy\Support\TenantContext::class)->tenantId())->value('message_credits');
        if ($credits < 50) {
            $alerts[] = ['type' => 'credits', 'level' => 'warning', 'text' => "Message credits are low ({$credits}).", 'link' => '/marketing?tab=credits'];
        }
        if ($now->hour >= 12) {
            foreach (\App\Shops\Models\Shop::query()->when($shopId, fn ($q) => $q->whereKey($shopId))->get() as $shop) {
                if (! Invoice::where('shop_id', $shop->id)->whereBetween('created_at', [$now->startOfDay()->utc(), $now->endOfDay()->utc()])->exists()) {
                    $alerts[] = ['type' => 'no_bills', 'level' => 'warning', 'text' => "No bill yet today at {$shop->name}.", 'link' => '/pos'];
                }
            }
        }
        $waiting = \App\Bot\Models\WaitingEntry::where('status', 'waiting')->when($shopId, fn ($q) => $q->where('shop_id', $shopId))->count();
        if ($waiting) {
            $alerts[] = ['type' => 'waiting', 'level' => 'info', 'text' => "{$waiting} customer(s) checked in by QR and waiting.", 'link' => '/pos'];
        }

        return $alerts;
    }
}
