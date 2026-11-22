<?php

namespace App\Messaging\Services;

use App\Customers\Models\Customer;
use App\Messaging\Models\Automation;
use App\Messaging\Models\MessageLog;
use App\Shops\Models\Shop;
use App\Tenancy\Models\Tenant;
use App\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/** Scheduled automations. Deduped through message_log context so re-running a day never double-sends. */
class AutomationRunner
{
    public function __construct(private MessageSender $sender) {}

    public function runAll(): array
    {
        $out = [];
        foreach (Tenant::where('status', '!=', 'suspended')->pluck('id') as $tenantId) {
            app(TenantContext::class)->set($tenantId);
            $out[$tenantId] = $this->runTenant($tenantId);
        }
        app(TenantContext::class)->clear();

        return $out;
    }

    public function runTenant(int $tenantId): int
    {
        $sent = 0;
        $shop = Shop::query()->value('name') ?? '';
        foreach (Automation::where('active', true)->whereNotNull('template_id')->get() as $a) {
            $tpl = \App\Messaging\Models\MessageTemplate::find($a->template_id);
            if (! $tpl) {
                continue;
            }
            foreach ($this->audience($a->trigger) as [$customer, $key]) {
                if (MessageLog::where('context->key', $key)->exists()) {
                    continue;
                }
                $r = $this->sender->toCustomer($customer, $tpl, [$customer->name, $shop, '', ''], "automation:{$a->trigger}");
                if ($r['ok']) {
                    MessageLog::where('provider_msg_id', $r['id'])->update(['context' => json_encode(['key' => $key, 'automation' => $a->trigger])]);
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /** @return iterable<array{0:Customer,1:string}> */
    private function audience(string $trigger): iterable
    {
        $today = now('Asia/Kolkata');
        switch ($trigger) {
            case 'birthday':
            case 'anniversary':
                $col = $trigger === 'birthday' ? 'birthday' : 'anniversary';
                foreach (Customer::query()->contactable()->whereMonth($col, $today->month)->whereDay($col, $today->day)->get() as $c) {
                    yield [$c, "{$trigger}:{$c->id}:{$today->year}"];
                }
                break;
            case 'winback':
                foreach (CustomerFilter::audience(['inactive_days' => 45], 'whatsapp')->limit(200)->get() as $c) {
                    yield [$c, "winback:{$c->id}:".$today->format('Y-m')];
                }
                break;
            case 'due_reminder':
                $ids = DB::table('invoices')->where('tenant_id', app(TenantContext::class)->tenantId())->where('status', 'due')->pluck('customer_id')->filter()->unique();
                foreach (Customer::query()->contactable()->whereIn('id', $ids)->get() as $c) {
                    yield [$c, "due_reminder:{$c->id}:".$today->format('o-W')];
                }
                break;
        }
    }
}
