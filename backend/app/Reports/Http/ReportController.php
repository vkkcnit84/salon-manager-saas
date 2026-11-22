<?php

namespace App\Reports\Http;

use App\Reports\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Owner-only: reports expose revenue and commission. Without X-Shop-Id the owner sees all shops. */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    private function shopId(Request $r): ?int
    {
        abort_unless($r->user()->is_owner, 403, 'Reports are available to the owner only.');

        return $r->attributes->get('shop')?->id;
    }

    public function dashboard(Request $r)
    {
        return $this->reports->dashboard($this->shopId($r));
    }

    public function sales(Request $r)
    {
        $shop = $this->shopId($r);
        [$s, $e] = $this->reports->range($r->query('from'), $r->query('to'));

        return $this->reports->sales($shop, $s, $e);
    }

    public function staff(Request $r)
    {
        $shop = $this->shopId($r);
        [$s, $e] = $this->reports->range($r->query('from'), $r->query('to'));

        return $this->reports->staff($shop, $s, $e);
    }

    public function table(Request $r, string $type, \App\Reports\Services\ReportTables $tables)
    {
        $shop = $this->shopId($r);
        [$s, $e] = $this->reports->range($r->query('from'), $r->query('to'));

        return $tables->get($type, $shop, $s, $e);
    }

    public function export(Request $r, string $type, \App\Reports\Services\ReportTables $tables, \App\Reports\Services\ReportExporter $exporter)
    {
        $shop = $this->shopId($r);
        $format = $r->validate(['format' => ['required', 'in:csv,xlsx,pdf']])['format'];
        [$s, $e] = $this->reports->range($r->query('from'), $r->query('to'));

        return $exporter->download($tables->get($type, $shop, $s, $e), $format, "salon-{$type}-{$s->toDateString()}_{$e->toDateString()}");
    }

    public function dues(Request $r)
    {
        return $this->reports->dues($this->shopId($r));
    }
}
