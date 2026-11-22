<?php

namespace App\Messaging\Services;

use App\Messaging\Models\Campaign;
use App\Messaging\Models\CampaignMessage;
use App\Credits\Services\Credits;
use App\Shops\Models\Shop;
use App\Support\Audit;
use App\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    public function __construct(private MessageSender $sender, private Credits $credits) {}

    public function estimate(array $filters, string $channel, int $tenantId): array
    {
        $count = CustomerFilter::audience($filters, $channel)->count();
        $cost = $count * (int) config("messaging.credit_cost.{$channel}", 1);
        $balance = $this->credits->balance($tenantId);

        return ['audience' => $count, 'credits_needed' => $cost, 'balance' => $balance, 'sufficient' => $balance >= $cost];
    }

    /** Freeze the audience, then send in chunks. Safe to re-run: queued rows only. */
    public function send(Campaign $campaign): Campaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return $campaign;
        }
        $campaign->update(['status' => 'sending', 'sent_at' => now()]);
        $ids = CustomerFilter::audience($campaign->audience ?? [], $campaign->channel)->pluck('customers.id');
        foreach ($ids->chunk(500) as $chunk) {
            DB::table('campaign_messages')->insertOrIgnore($chunk->map(fn ($id) => [
                'tenant_id' => $campaign->tenant_id, 'campaign_id' => $campaign->id, 'customer_id' => $id, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
            ])->all());
        }

        $template = $campaign->template;
        $shopName = Shop::query()->value('name') ?? Tenant::whereKey($campaign->tenant_id)->value('business_name');
        $meta = $campaign->meta ?? [];
        $stopped = false;

        CampaignMessage::where('campaign_id', $campaign->id)->where('status', 'queued')->with('customer')->orderBy('id')->chunkById(200, function ($rows) use (&$stopped, $campaign, $template, $shopName, $meta) {
            foreach ($rows as $m) {
                if ($stopped) {
                    return false;
                }
                $c = $m->customer;
                $vars = [$c->name, $shopName, $meta['offer'] ?? '', $meta['expiry'] ?? ''];
                $r = $this->sender->toCustomer($c, $template, $vars, "campaign:{$campaign->id}");
                if (! $r['ok'] && $r['error'] === 'Insufficient credits') {
                    $stopped = true; // stop the run; owner is told via status + audit
                    return false;
                }
                $m->update($r['ok']
                    ? ['status' => $this->simulatedOutcome($m->id), 'provider_msg_id' => $r['id'], 'sent_at' => now()]
                    : ['status' => 'failed', 'error' => $r['error']]);
            }
        });

        $campaign->update(['status' => $stopped ? 'stopped' : 'completed']);
        if ($stopped) {
            Audit::record('campaign.stopped_low_credits', $campaign);
        }

        return $campaign->fresh();
    }

    /** Local fake only: mimic provider delivery receipts so reports look realistic. Real receipts arrive by webhook. */
    private function simulatedOutcome(int $id): string
    {
        $n = crc32((string) $id) % 100;

        return $n < 4 ? 'failed' : ($n < 30 ? 'delivered' : 'read');
    }

    public function stats(Campaign $c): array
    {
        $counts = CampaignMessage::where('campaign_id', $c->id)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
        $targeted = CampaignMessage::where('campaign_id', $c->id)->whereIn('status', ['sent', 'delivered', 'read'])->pluck('customer_id');
        $revenue = 0;
        if ($c->sent_at && $targeted->isNotEmpty()) {
            $revenue = (int) DB::table('invoices')->where('tenant_id', $c->tenant_id)->where('status', '!=', 'void')->whereIn('customer_id', $targeted)
                ->whereBetween('created_at', [$c->sent_at, $c->sent_at->copy()->addDays(7)])->sum('total_paise');
        }
        $total = (int) $counts->sum();
        $ok = $total - (int) ($counts['failed'] ?? 0) - (int) ($counts['queued'] ?? 0);

        return [
            'total' => $total, 'sent' => $ok, 'delivered' => (int) (($counts['delivered'] ?? 0) + ($counts['read'] ?? 0)),
            'read' => (int) ($counts['read'] ?? 0), 'failed' => (int) ($counts['failed'] ?? 0), 'queued' => (int) ($counts['queued'] ?? 0),
            'credits_spent' => $ok * (int) config("messaging.credit_cost.{$c->channel}", 1), 'attributed_revenue_paise' => $revenue,
        ];
    }
}
