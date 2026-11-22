<?php

namespace App\Reports\Services;

use App\Messaging\Models\Campaign;
use App\Messaging\Services\CampaignService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Tabular form of every report: ['title','columns','rows','summary']. Money columns carry paise (ints);
 * the 'money' list tells the UI/export which columns to format as rupees.
 */
class ReportTables
{
    public const TYPES = ['sales', 'services', 'staff', 'payments', 'dues', 'pnl', 'customers', 'discounts', 'gst', 'expenses', 'campaigns'];

    public function __construct(private ReportService $r) {}

    public function get(string $type, ?int $shopId, CarbonImmutable $a, CarbonImmutable $b): array
    {
        abort_unless(in_array($type, self::TYPES, true), 404, 'Unknown report.');

        return $this->{'t'.ucfirst($type)}($shopId, $a, $b);
    }

    private function inv(?int $shopId, CarbonImmutable $a, CarbonImmutable $b)
    {
        return DB::table('invoices')->where('tenant_id', $this->tenant())->where('status', '!=', 'void')->when($shopId, fn ($q) => $q->where('shop_id', $shopId))->whereBetween('created_at', [$a->utc(), $b->utc()]);
    }

    private function tenant(): int
    {
        return (int) app(\App\Tenancy\Support\TenantContext::class)->tenantId();
    }

    private function table(string $title, array $columns, array $rows, array $money = [], array $summary = []): array
    {
        return compact('title', 'columns', 'rows', 'money', 'summary');
    }

    private function tSales($s, $a, $b): array
    {
        $x = $this->r->sales($s, $a, $b);

        return $this->table('Daily sales', ['Date', 'Bills', 'Sales'], array_map(fn ($d) => [$d['date'], $d['bills'], $d['sales']], $x['daily']), [2], ['Total sales' => $x['totals']['sales'], 'Bills' => $x['totals']['bills']]);
    }

    private function tServices($s, $a, $b): array
    {
        $ids = $this->inv($s, $a, $b)->pluck('id');
        $rows = DB::table('invoice_items')->whereIn('invoice_id', $ids)->selectRaw('item_type, name, sum(qty) q, sum(total_paise) t')->groupBy('item_type', 'item_id', 'name', 'item_type')->orderByDesc('t')->get()
            ->map(fn ($r) => [ucfirst($r->item_type), $r->name, (int) $r->q, (int) $r->t])->all();

        return $this->table('Service & product wise', ['Type', 'Item', 'Qty', 'Revenue'], $rows, [3]);
    }

    private function tStaff($s, $a, $b): array
    {
        $rows = array_map(fn ($r) => [$r['name'], $r['services'], $r['revenue'], $r['commission']], $this->r->staff($s, $a, $b));

        return $this->table('Staff performance & commission', ['Staff', 'Services', 'Revenue', 'Commission'], $rows, [2, 3], ['Total commission' => array_sum(array_column($rows, 3))]);
    }

    private function tPayments($s, $a, $b): array
    {
        $x = $this->r->sales($s, $a, $b);

        return $this->table('Payment modes', ['Mode', 'Payments', 'Amount'], array_map(fn ($m) => [strtoupper($m['method']), $m['count'], $m['amount']], $x['payment_methods']), [2], ['Collected' => $x['totals']['collected'], 'Due' => $x['totals']['due']]);
    }

    private function tDues($s, $a, $b): array
    {
        $rows = array_map(fn ($d) => [$d['number'], $d['date'], $d['customer'], $d['mobile'] ?? '', $d['total'], $d['due']], $this->r->dues($s));

        return $this->table('Outstanding dues', ['Bill', 'Date', 'Customer', 'Mobile', 'Total', 'Due'], $rows, [4, 5], ['Total due' => array_sum(array_column($rows, 5))]);
    }

    private function tPnl($s, $a, $b): array
    {
        $ids = $this->inv($s, $a, $b)->pluck('id');
        $net = (int) DB::table('invoice_items')->whereIn('invoice_id', $ids)->sum('taxable_paise');
        $com = (int) DB::table('invoice_items')->whereIn('invoice_id', $ids)->sum('commission_paise');
        $exp = DB::table('expenses')->where('tenant_id', $this->tenant())->when($s, fn ($q) => $q->where('shop_id', $s))->whereBetween('spent_on', [$a->toDateString(), $b->toDateString()])->selectRaw('category, sum(amount_paise) t')->groupBy('category')->pluck('t', 'category');
        $salaries = (int) ($exp['Salaries'] ?? 0);
        $otherExp = (int) $exp->sum() - $salaries;
        $profit = $net - $com - $salaries - $otherExp;
        $rows = [['Net sales (excl. GST)', $net], ['Less: staff commission', -$com], ['Less: salaries', -$salaries], ['Less: other expenses', -$otherExp], ['Net profit', $profit]];

        return $this->table('Profit & loss', ['Line', 'Amount'], $rows, [1], ['Net profit' => $profit, 'Margin %' => $net ? round($profit / $net * 100, 1) : 0]);
    }

    private function tCustomers($s, $a, $b): array
    {
        $t = $this->tenant();
        $rows = $this->inv($s, $a, $b)->whereNotNull('customer_id')->selectRaw('customer_id, count(*) n, sum(total_paise) spend, max(created_at) last')->groupBy('customer_id')->orderByDesc('spend')->limit(25)->get();
        $names = DB::table('customers')->whereIn('id', $rows->pluck('customer_id'))->pluck('name', 'id');
        $first = DB::table('invoices')->where('tenant_id', $t)->whereIn('customer_id', $rows->pluck('customer_id'))->groupBy('customer_id')->selectRaw('customer_id, min(created_at) f')->pluck('f', 'customer_id');
        $out = $rows->map(fn ($r) => [$names[$r->customer_id] ?? '—', (int) $r->n, (int) $r->spend, CarbonImmutable::parse($r->last)->setTimezone(ReportService::TZ)->toDateString(), CarbonImmutable::parse($first[$r->customer_id])->gte($a->utc()) ? 'New' : 'Returning'])->all();
        $lost = DB::table('customers')->where('tenant_id', $t)->whereNull('deleted_at')->whereRaw("(select max(created_at) from invoices where invoices.customer_id = customers.id) < ?", [now()->subDays(60)])->count();

        return $this->table('Top customers', ['Customer', 'Visits', 'Spend', 'Last visit', 'Type'], $out, [2], ['New customers' => count(array_filter($out, fn ($r) => $r[4] === 'New')), 'Lost (60+ days)' => $lost]);
    }

    private function tDiscounts($s, $a, $b): array
    {
        $rows = $this->inv($s, $a, $b)->where('discount_paise', '>', 0)->orderByDesc('discount_paise')->limit(200)->get(['number', 'created_at', 'subtotal_paise', 'discount_paise'])
            ->map(fn ($r) => [$r->number, CarbonImmutable::parse($r->created_at)->setTimezone(ReportService::TZ)->toDateString(), (int) $r->subtotal_paise, (int) $r->discount_paise, round($r->discount_paise / max(1, $r->subtotal_paise) * 100, 1).'%'])->all();

        return $this->table('Discounts given', ['Bill', 'Date', 'Subtotal', 'Discount', '% off'], $rows, [2, 3], ['Total discount' => array_sum(array_column($rows, 3))]);
    }

    private function tGst($s, $a, $b): array
    {
        $rows = $this->inv($s, $a, $b)->orderBy('id')->limit(2000)->get(['id', 'number', 'created_at', 'total_paise', 'tax_paise', 'tip_paise', 'rounding_paise'])->map(function ($r) {
            $taxable = $r->total_paise - $r->tax_paise - $r->tip_paise - $r->rounding_paise;
            $cgst = intdiv($r->tax_paise, 2);

            return [$r->number, CarbonImmutable::parse($r->created_at)->setTimezone(ReportService::TZ)->toDateString(), $taxable, $cgst, $r->tax_paise - $cgst, (int) $r->total_paise];
        })->all();

        return $this->table('GST report (invoice-wise)', ['Bill', 'Date', 'Taxable value', 'CGST', 'SGST', 'Invoice total'], $rows, [2, 3, 4, 5], ['Total GST' => array_sum(array_column($rows, 3)) + array_sum(array_column($rows, 4))]);
    }

    private function tExpenses($s, $a, $b): array
    {
        $rows = DB::table('expenses')->where('tenant_id', $this->tenant())->when($s, fn ($q) => $q->where('shop_id', $s))->whereBetween('spent_on', [$a->toDateString(), $b->toDateString()])->orderByDesc('spent_on')->get(['spent_on', 'category', 'note', 'amount_paise'])
            ->map(fn ($r) => [substr((string) $r->spent_on, 0, 10), $r->category, $r->note ?? '', (int) $r->amount_paise])->all();

        return $this->table('Expenses', ['Date', 'Category', 'Note', 'Amount'], $rows, [3], ['Total expenses' => array_sum(array_column($rows, 3))]);
    }

    private function tCampaigns($s, $a, $b): array
    {
        $svc = app(CampaignService::class);
        $rows = Campaign::whereIn('status', ['completed', 'stopped'])->latest('id')->limit(50)->get()->map(function ($c) use ($svc) {
            $st = $svc->stats($c);
            $costPaise = $st['credits_spent'] * 100; // 1 credit ≈ ₹1 at the standard pack rate

            return [$c->name, strtoupper($c->channel), $st['sent'], $st['read'], $st['failed'], $st['credits_spent'], $st['attributed_revenue_paise'], $costPaise ? round($st['attributed_revenue_paise'] / $costPaise, 1).'x' : '—'];
        })->all();

        return $this->table('Campaign ROI', ['Campaign', 'Channel', 'Sent', 'Read', 'Failed', 'Credits', 'Attributed revenue', 'ROI'], $rows, [6]);
    }
}
