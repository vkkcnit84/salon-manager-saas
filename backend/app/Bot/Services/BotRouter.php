<?php

namespace App\Bot\Services;

use App\Bot\Models\WaitingEntry;
use App\Customers\Models\Customer;
use App\Messaging\Models\Campaign;
use App\Messaging\Models\MessageLog;
use App\Messaging\Models\MessageTemplate;
use App\Messaging\Services\CampaignService;
use App\Models\User;
use App\Reports\Services\ReportService;
use App\Shared\Support\Money;
use App\Shops\Models\Shop;
use App\Staff\Models\Staff;
use App\Support\Audit;
use App\Support\Permit;
use App\Tenancy\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Inbound WhatsApp message -> replies. Identifies the sender by mobile, then dispatches by role.
 * Replies inside the 24h customer-service window are free text (no credits); see Pusher for outbound.
 */
class BotRouter
{
    public function __construct(private ReportService $reports, private CampaignService $campaigns) {}

    public const MENU = [
        ['id' => 'm:today', 'title' => "Today's sales"], ['id' => 'm:month', 'title' => 'This month'], ['id' => 'm:commission', 'title' => 'Staff commission'],
        ['id' => 'm:dues', 'title' => 'Dues'], ['id' => 'm:offer', 'title' => 'Send offer'], ['id' => 'm:help', 'title' => 'Help'],
    ];

    /** @return array<int,array{text:string,buttons?:array}> */
    /**
     * @param  ?int  $tenantId  When set (the in-app simulator) the sender may only be someone in that tenant, so one
     *                          salon can never act as another's owner. The signed webhook leaves it null (identity by number).
     */
    public function handle(string $from, string $text, ?string $buttonId = null, ?int $tenantId = null): array
    {
        $wa = $this->normalise($from);
        $input = trim($buttonId ?: $text);

        if ($tenantId !== null) {
            app(TenantContext::class)->set($tenantId);
        }
        if (preg_match('/^(stop|unsubscribe)$/i', $input)) {
            Customer::withoutGlobalScopes()->where('mobile', $wa)->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->update(['opted_out_at' => now()]);

            return $this->out($wa, [['text' => "You've been unsubscribed. You won't receive offers from us any more."]]);
        }

        [$type, $actor] = $this->identify($wa, $tenantId);
        if ($actor?->tenant_id ?? null) {
            app(TenantContext::class)->set((int) $actor->tenant_id);
        }
        $this->log($wa, 'in', $input, null);

        $replies = match ($type) {
            'owner', 'manager' => $this->forManager($actor, $type, $input),
            'staff' => $this->forStaff($actor, $input),
            'staff_user' => [['text' => "Hi {$actor->name}! Business reports are available to owners and managers."]],
            'customer' => $this->forCustomer($wa, $actor, $input),
            default => $this->forUnknown($wa, $input, null, $tenantId),
        };

        return $this->out($wa, $replies);
    }

    public function identify(string $wa, ?int $tenantId = null): array
    {
        if ($u = User::where('mobile', $wa)->whereNotNull('tenant_id')->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->first()) {
            return [Permit::roleOf($u) === 'owner' ? 'owner' : (Permit::roleOf($u) === 'manager' ? 'manager' : 'staff_user'), $u];
        }
        if ($s = Staff::withoutGlobalScopes()->where('mobile', $wa)->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->first()) {
            return ['staff', $s];
        }
        if ($c = Customer::withoutGlobalScopes()->where('mobile', $wa)->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->first()) {
            return ['customer', $c];
        }

        return ['unknown', null];
    }

    // ---------- owner / manager ----------
    private function forManager(User $u, string $role, string $in): array
    {
        $k = mb_strtolower($in);
        $shopId = $role === 'owner' ? null : DB::table('shop_user')->where('user_id', $u->id)->value('shop_id');

        if (preg_match('/^winback:(\d+):(send|skip)$/', $in, $m)) {
            return $this->approveWinback($u, (int) $m[1], $m[2]);
        }
        // Free-form questions go to the assistant first; only exact one-word shortcuts and button ids use the fixed menu.
        $shortcut = (bool) preg_match('/^(m:\w+|menu|hi|hello|help|namaste|today|aaj|sales|month|mahina|mahine|commission|staff|dues|baki|udhaar|offer|winback)$/', $k);
        if (! $shortcut && $role === 'owner') {
            $a = app(\App\Assistant\Services\AssistantService::class)->ask($u, $in);
            if ($a['tool']) {
                return [['text' => $a['answer'], 'buttons' => collect($a['buttons'])->map(fn ($b) => ['id' => 'winback:'.explode(':', $b['id'])[1].':'.explode(':', $b['id'])[0], 'title' => $b['title']])->all()]];
            }
        }
        if (preg_match('/^(menu|hi|hello|help|namaste|m:help)/', $k)) {
            return [['text' => "Namaste {$u->name}! What would you like to see?", 'buttons' => self::MENU]];
        }
        if (preg_match('/m:today|today|aaj|sales/', $k)) {
            $t = $this->period($shopId, 'today');

            return [['text' => "📊 Today: ".Money::paise($t['sales'])->format()." from {$t['bills']} bills (avg ".Money::paise($t['avg_bill'])->format().').']];
        }
        if (preg_match('/m:month|month|mahine|mahina/', $k)) {
            $t = $this->period($shopId, 'month');

            return [['text' => '📅 This month: '.Money::paise($t['sales'])->format()." from {$t['bills']} bills."]];
        }
        if (preg_match('/m:commission|commission|staff/', $k)) {
            if (! Permit::userCan($u, 'commission.view') && $role !== 'owner') {
                return [['text' => "You don't have permission to see commission."]];
            }
            $rows = $this->reports->staff($shopId, ...$this->range('month'));
            if (! $rows) {
                return [['text' => 'No staff sales this month yet.']];
            }

            return [['text' => "💰 Commission this month:\n".collect($rows)->take(6)->map(fn ($r) => "• {$r['name']}: ".Money::paise($r['commission'])->format())->implode("\n")]];
        }
        if (preg_match('/m:dues|dues|baki|udhaar/', $k)) {
            $d = collect($this->reports->dues($shopId));

            return [['text' => '🧾 Pending dues: '.Money::paise((int) $d->sum('due'))->format().' across '.$d->count().' bills.']];
        }
        if (preg_match('/m:offer|offer|winback|win-back/', $k)) {
            return $this->draftWinback($u);
        }

        if ($role === 'owner') { // anything else is a natural-language question (English / Hinglish / Hindi)
            $a = app(\App\Assistant\Services\AssistantService::class)->ask($u, $in);
            if ($a['tool']) {
                return [['text' => $a['answer'], 'buttons' => collect($a['buttons'])->map(fn ($b) => ['id' => str_replace(['send:', 'skip:'], ['winback:', 'winback:'], $b['id']).(str_starts_with($b['id'], 'send:') ? ':send' : ':skip'), 'title' => $b['title']])->all()]];
            }
        }

        return [['text' => "Sorry, I didn't get that. Type *menu* to see options.", 'buttons' => [['id' => 'm:help', 'title' => 'Menu']]]];
    }

    private function draftWinback(User $u): array
    {
        $tpl = MessageTemplate::where('channel', 'whatsapp')->where('category', 'marketing')->where('status', 'approved')->orderBy('id')->first();
        if (! $tpl) {
            return [['text' => 'Create an approved marketing template first (Marketing → Templates).']];
        }
        $filters = ['inactive_days' => 45];
        $c = Campaign::create(['name' => 'Win-back (via WhatsApp)', 'template_id' => $tpl->id, 'channel' => 'whatsapp', 'audience' => $filters, 'meta' => ['offer' => 'Flat 15% off this week', 'expiry' => now()->addDays(7)->format('d M')], 'status' => 'draft', 'created_by' => $u->id]);
        $e = $this->campaigns->estimate($filters, 'whatsapp', $u->tenant_id);

        return [['text' => "📣 Draft ready: win-back offer to {$e['audience']} customers who haven't visited in 45 days.\nCost: {$e['credits_needed']} credits (you have {$e['balance']}).", 'buttons' => [['id' => "winback:{$c->id}:send", 'title' => 'Send now'], ['id' => "winback:{$c->id}:skip", 'title' => 'Skip']]]];
    }

    private function approveWinback(User $u, int $id, string $act): array
    {
        if (! Permit::userCan($u, 'campaigns.manage')) {
            Audit::record('bot.denied', null, ['button' => "winback:{$id}:{$act}"], $u->id);

            return [['text' => "You don't have permission to send campaigns."]];
        }
        $c = Campaign::find($id);
        if (! $c || $c->status !== 'draft') {
            return [['text' => 'That draft is no longer available.']];
        }
        if ($act === 'skip') {
            $c->update(['status' => 'stopped']);
            Audit::record('bot.campaign_skipped', $c, [], $u->id);

            return [['text' => 'Okay, skipped.']];
        }
        $done = $this->campaigns->send($c);
        Audit::record('bot.campaign_sent', $c, ['status' => $done->status], $u->id);
        $s = $this->campaigns->stats($done);

        return [['text' => "✅ Sent to {$s['sent']} customers ({$s['failed']} failed). Status: {$done->status}."]];
    }

    // ---------- staff ----------
    private function forStaff(Staff $s, string $in): array
    {
        if (preg_match('/earn|kamai|commission|today|aaj/i', $in)) {
            [$a, $b] = $this->range('month');
            $rows = collect($this->reports->staff(null, $a, $b))->firstWhere('staff_id', $s->id);
            $c = $rows['commission'] ?? 0;

            return [['text' => "Hi {$s->name}! This month: ".($rows['services'] ?? 0).' services, earned '.Money::paise($c)->format().". Pending payout: ".Money::paise($c)->format().'.']];
        }

        return [['text' => "Hi {$s->name}! Reply *earnings* to see your month so far."]];
    }

    // ---------- customer ----------
    private function forCustomer(string $wa, Customer $c, string $in): array
    {
        return $this->forUnknown($wa, $in, $c, (int) $c->tenant_id);
    }

    private function forUnknown(string $wa, string $in, ?Customer $known = null, ?int $tenantId = null): array
    {
        $session = DB::table('bot_sessions')->where('wa_id', $wa)->where('expires_at', '>', now())->orderByDesc('id')->first();

        if (preg_match('/^CHECKIN-([A-Z0-9]+)$/i', $in, $m)) {
            // Public code is globally unique; when the caller is bound to a tenant, the shop must belong to it.
            $shop = Shop::withoutGlobalScopes()->where('code', strtoupper($m[1]))->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->first();
            if (! $shop) {
                return [['text' => "We couldn't find that salon. Please scan the QR again."]];
            }
            app(TenantContext::class)->set((int) $shop->tenant_id, $shop->id);
            $c = $known && $known->tenant_id === $shop->tenant_id ? $known : Customer::firstOrCreate(['mobile' => $wa], ['name' => $wa, 'source' => 'whatsapp_qr']);
            $isNew = $c->name === $wa;
            $this->saveSession($wa, $shop->tenant_id, $isNew ? 'ask_name' : ($c->consent_at ? 'done' : 'ask_consent'), ['shop_id' => $shop->id, 'customer_id' => $c->id]);
            if ($isNew) {
                return [['text' => "Welcome to {$shop->name}! 🙏 What's your name?"]];
            }
            if (! $c->consent_at) {
                return [$this->consentPrompt($c->name, $shop->name)];
            }

            return $this->enqueue($shop, $c);
        }

        if ($session && $session->state === 'ask_name') {
            $ctx = json_decode($session->context, true);
            app(TenantContext::class)->set((int) $session->tenant_id);
            $c = Customer::find($ctx['customer_id']);
            $c->update(['name' => mb_substr(trim($in), 0, 120)]);
            $this->saveSession($wa, $session->tenant_id, 'ask_consent', $ctx);

            return [$this->consentPrompt($c->name, Shop::find($ctx['shop_id'])->name)];
        }
        if ($session && $session->state === 'ask_consent' && preg_match('/^(consent:)?(yes|no)$/i', $in, $m)) {
            $ctx = json_decode($session->context, true);
            app(TenantContext::class)->set((int) $session->tenant_id);
            $c = Customer::find($ctx['customer_id']);
            $c->update(['consent_whatsapp' => strtolower($m[2]) === 'yes', 'consent_at' => now()]);
            $this->saveSession($wa, $session->tenant_id, 'done', $ctx);

            return $this->enqueue(Shop::find($ctx['shop_id']), $c);
        }

        return [['text' => "Hi! 👋 Please scan the salon's QR code to check in."]];
    }

    private function consentPrompt(string $name, string $shop): array
    {
        return ['text' => "Thanks {$name}! May we send you bills and occasional offers from {$shop} on WhatsApp? Reply STOP any time to opt out.", 'buttons' => [['id' => 'consent:yes', 'title' => 'Yes'], ['id' => 'consent:no', 'title' => 'No']]];
    }

    private function enqueue(Shop $shop, Customer $c): array
    {
        $e = WaitingEntry::firstOrCreate(['shop_id' => $shop->id, 'customer_id' => $c->id, 'status' => 'waiting']);
        $pos = WaitingEntry::where('shop_id', $shop->id)->where('status', 'waiting')->where('id', '<=', $e->id)->count();

        return [['text' => "You're checked in at {$shop->name}, {$c->name}. ✅ Queue position: #{$pos}."]];
    }

    private function saveSession(string $wa, int $tenantId, string $state, array $ctx): void
    {
        DB::table('bot_sessions')->where('wa_id', $wa)->delete();
        DB::table('bot_sessions')->insert(['tenant_id' => $tenantId, 'wa_id' => $wa, 'actor_type' => 'customer', 'state' => $state, 'context' => json_encode($ctx), 'expires_at' => now()->addHours(2), 'created_at' => now(), 'updated_at' => now()]);
    }

    // ---------- helpers ----------
    private function range(string $p): array
    {
        $now = CarbonImmutable::now(ReportService::TZ);

        return $p === 'today' ? [$now->startOfDay(), $now->endOfDay()] : [$now->startOfMonth(), $now->endOfDay()];
    }

    private function period(?int $shopId, string $p): array
    {
        return $this->reports->sales($shopId, ...$this->range($p))['totals'];
    }

    private function normalise(string $n): string
    {
        return substr(preg_replace('/\D/', '', $n), -10);
    }

    private function out(string $wa, array $replies): array
    {
        foreach ($replies as $r) {
            $this->log($wa, 'out', $r['text'], $r['buttons'] ?? null);
        }

        return $replies;
    }

    private function log(string $wa, string $dir, string $text, ?array $buttons): void
    {
        $tenant = app(TenantContext::class)->tenantId();
        if ($tenant) {
            MessageLog::create(['tenant_id' => $tenant, 'channel' => 'whatsapp', 'direction' => $dir, 'to' => $wa, 'body' => $text, 'buttons' => $buttons, 'status' => $dir === 'in' ? 'received' : 'sent', 'context' => ['bot' => true]]);
        }
    }
}
