<?php

namespace App\Bot\Services;

use App\Models\User;
use App\Reports\Services\ReportService;
use App\Shared\Support\Money;
use App\Shops\Models\Shop;
use App\Tenancy\Models\Tenant;
use App\Tenancy\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OwnerDigest
{
    public function __construct(private ReportService $reports, private Pusher $pusher) {}

    public function summaryText(int $tenantId): string
    {
        $now = CarbonImmutable::now(ReportService::TZ);
        $lines = [];
        foreach (Shop::all() as $shop) {
            $t = $this->reports->sales($shop->id, $now->startOfDay(), $now->endOfDay());
            $cash = collect($t['payment_methods'])->keyBy('method');
            $lines[] = "• {$shop->name}: ".Money::paise($t['totals']['sales'])->format()." ({$t['totals']['bills']} bills) · cash ".Money::paise((int) ($cash['cash']['amount'] ?? 0))->format().' · UPI '.Money::paise((int) ($cash['upi']['amount'] ?? 0))->format();
        }
        $top = $this->reports->staff(null, $now->startOfDay(), $now->endOfDay())[0] ?? null;
        $dues = collect($this->reports->dues(null))->sum('due');

        return "🌙 Daily summary — ".$now->format('d M')."\n".implode("\n", $lines).($top ? "\nTop staff: {$top['name']} (".Money::paise($top['revenue'])->format().')' : '')."\nDues: ".Money::paise((int) $dues)->format();
    }

    /** @return int number of messages sent */
    public function sendNightly(bool $force = false): int
    {
        $sent = 0;
        $slot = now('Asia/Kolkata')->format('H:').(now('Asia/Kolkata')->minute < 30 ? '00' : '30');
        foreach (Tenant::where('status', '!=', 'suspended')->pluck('id') as $tid) {
            app(TenantContext::class)->set($tid);
            foreach (User::where('tenant_id', $tid)->whereNotNull('mobile')->get() as $u) {
                $prefs = $u->bot_prefs ?? [];
                if (empty($prefs['nightly']) || (! $force && ($prefs['nightly_time'] ?? '22:00') !== $slot)) {
                    continue;
                }
                if (! $force && ! Cache::add("bot:nightly:{$u->id}:".now()->toDateString(), 1, 86400)) {
                    continue;
                }
                $sent += (int) $this->pusher->push($tid, $u->mobile, $this->summaryText($tid), 'summary')['ok'];
            }
        }
        app(TenantContext::class)->clear();

        return $sent;
    }

    /** Low credits, and "no bill by 12:00" alerts to owners. Deduped per day. */
    public function sendAlerts(): int
    {
        $sent = 0;
        $now = now('Asia/Kolkata');
        foreach (Tenant::where('status', '!=', 'suspended')->get() as $t) {
            app(TenantContext::class)->set($t->id);
            $owner = User::find($t->owner_user_id);
            if (! $owner?->mobile) {
                continue;
            }
            $alerts = [];
            if ($t->message_credits < 50) {
                $alerts[] = "⚠️ Message credits are low ({$t->message_credits}). Top up to keep campaigns and alerts running.";
            }
            if ($now->hour >= 12) {
                foreach (Shop::all() as $shop) {
                    $n = DB::table('invoices')->where('tenant_id', $t->id)->where('shop_id', $shop->id)->whereBetween('created_at', [$now->startOfDay()->utc(), $now->endOfDay()->utc()])->count();
                    if ($n === 0) {
                        $alerts[] = "⚠️ No bill yet today at {$shop->name}.";
                    }
                }
            }
            foreach ($alerts as $a) {
                if (Cache::add('bot:alert:'.md5($t->id.$a).':'.$now->toDateString(), 1, 86400)) {
                    $sent += (int) $this->pusher->push($t->id, $owner->mobile, $a, 'alert')['ok'];
                }
            }
        }
        app(TenantContext::class)->clear();

        return $sent;
    }
}
