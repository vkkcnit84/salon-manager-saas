<?php

namespace App\Messaging\Http;

use App\Credits\Models\CreditTransaction;
use App\Credits\Services\Credits;
use App\Credits\Services\PaymentGateway;
use App\Messaging\Models\Automation;
use App\Messaging\Models\Campaign;
use App\Messaging\Models\Coupon;
use App\Messaging\Models\MessageLog;
use App\Messaging\Models\MessageTemplate;
use App\Messaging\Services\CampaignService;
use App\Messaging\Services\CustomerFilter;
use App\Messaging\Jobs\SendCampaignJob;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class MarketingController extends Controller
{
    public const TRIGGERS = ['invoice_created', 'birthday', 'anniversary', 'winback', 'due_reminder', 'feedback'];

    private function gate(Request $r): void
    {
        abort_unless($r->user()->is_owner || \App\Support\Permit::userCan($r->user(), 'campaigns.manage'), 403, 'You do not have permission to manage marketing.');
    }

    // ---- templates
    public function templates(Request $r)
    {
        $this->gate($r);

        return MessageTemplate::latest('id')->get();
    }

    public function storeTemplate(Request $r): JsonResponse
    {
        $this->gate($r);
        $d = $r->validate([
            'channel' => ['required', Rule::in(['whatsapp', 'sms'])], 'name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'category' => ['required', Rule::in(['marketing', 'utility'])], 'body' => ['required', 'string', 'max:1024'],
            'language' => ['sometimes', 'string', 'max:8'], 'dlt_template_id' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);
        // Local fake: templates are approved instantly. The real flow starts as `pending` until Meta/DLT approve.
        $t = MessageTemplate::create($d + ['variables' => ['name', 'shop', 'offer', 'expiry'], 'status' => 'approved', 'provider_template_id' => 'local_'.$d['name']]);
        Audit::record('template.created', $t);

        return response()->json($t, 201);
    }

    // ---- campaigns
    public function campaigns(Request $r)
    {
        $this->gate($r);
        $svc = app(CampaignService::class);

        return Campaign::with('template:id,name')->latest('id')->get()->map(fn ($c) => $c->setAttribute('stats', $svc->stats($c)));
    }

    public function estimate(Request $r, CampaignService $svc)
    {
        $this->gate($r);
        $d = $r->validate(['channel' => ['required', Rule::in(['whatsapp', 'sms'])], 'audience' => ['sometimes', 'array']]);

        return $svc->estimate($d['audience'] ?? [], $d['channel'], $r->user()->tenant_id);
    }

    public function storeCampaign(Request $r): JsonResponse
    {
        $this->gate($r);
        $d = $r->validate([
            'name' => ['required', 'string', 'max:120'], 'channel' => ['required', Rule::in(['whatsapp', 'sms'])],
            'template_id' => ['required', Rule::exists('message_templates', 'id')->where('tenant_id', $r->user()->tenant_id)->where('status', 'approved')],
            'audience' => ['sometimes', 'array'], 'meta' => ['sometimes', 'array'], 'scheduled_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);
        $c = Campaign::create($d + ['status' => ! empty($d['scheduled_at']) ? 'scheduled' : 'draft', 'created_by' => $r->user()->id]);

        return response()->json($c, 201);
    }

    public function sendCampaign(Request $r, int $id, CampaignService $svc)
    {
        $this->gate($r);
        $c = Campaign::findOrFail($id);
        abort_unless(in_array($c->status, ['draft', 'scheduled'], true), 422, 'This campaign has already been sent.');
        $est = $svc->estimate($c->audience ?? [], $c->channel, $r->user()->tenant_id);
        abort_if($est['audience'] === 0, 422, 'No consented customers match this audience.');
        abort_unless($est['sufficient'], 402, "Not enough credits: need {$est['credits_needed']}, have {$est['balance']}.");
        Audit::record('campaign.sent', $c, ['audience' => $est['audience']]);

        config('messaging.sync') ? SendCampaignJob::dispatchSync($c->id, $c->tenant_id) : SendCampaignJob::dispatch($c->id, $c->tenant_id);

        return $c->fresh()->setAttribute('stats', $svc->stats($c->fresh()));
    }

    public function skipCampaign(Request $r, int $id)
    {
        $this->gate($r);
        $c = Campaign::findOrFail($id);
        abort_unless($c->status === 'draft', 422, 'Only drafts can be skipped.');
        $c->update(['status' => 'stopped']);

        return $c;
    }

    public function campaignReport(Request $r, int $id, CampaignService $svc)
    {
        $this->gate($r);
        $c = Campaign::with('template')->findOrFail($id);
        $failed = \App\Messaging\Models\CampaignMessage::where('campaign_id', $id)->where('status', 'failed')->with('customer:id,name,mobile')->limit(50)->get();

        return ['campaign' => $c, 'stats' => $svc->stats($c), 'failed' => $failed];
    }

    // ---- automations
    public function automations(Request $r)
    {
        $this->gate($r);
        $existing = Automation::all()->keyBy('trigger');

        return collect(self::TRIGGERS)->map(fn ($t) => $existing[$t] ?? new Automation(['trigger' => $t, 'active' => false, 'delay_hours' => 0]))->values();
    }

    public function saveAutomation(Request $r, string $trigger)
    {
        $this->gate($r);
        abort_unless(in_array($trigger, self::TRIGGERS, true), 404);
        $d = $r->validate(['active' => ['sometimes', 'boolean'], 'delay_hours' => ['sometimes', 'integer', 'min:0', 'max:720'], 'template_id' => ['sometimes', 'nullable', Rule::exists('message_templates', 'id')->where('tenant_id', $r->user()->tenant_id)]]);
        $a = Automation::updateOrCreate(['trigger' => $trigger], $d);
        abort_if($a->active && ! $a->template_id, 422, 'Choose a template before turning this on.');

        return $a;
    }

    // ---- coupons
    public function coupons(Request $r)
    {
        $this->gate($r);

        return Coupon::latest('id')->get();
    }

    public function storeCoupon(Request $r): JsonResponse
    {
        $this->gate($r);
        $d = $r->validate([
            'code' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('coupons', 'code')->where('tenant_id', $r->user()->tenant_id)],
            'type' => ['required', Rule::in(['percent', 'flat'])], 'value' => ['required', 'integer', 'min:1'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'], 'expires_at' => ['sometimes', 'nullable', 'date'],
        ]);
        abort_if($d['type'] === 'percent' && $d['value'] > 10000, 422, 'Percent cannot exceed 100%.');

        return response()->json(Coupon::create(['code' => strtoupper($d['code'])] + $d), 201);
    }

    public function toggleCoupon(Request $r, int $id)
    {
        $this->gate($r);
        $c = Coupon::findOrFail($id);
        $c->update(['active' => $r->boolean('active')]);

        return $c;
    }

    public function validateCoupon(Request $r)
    {
        $c = Coupon::where('code', strtoupper((string) $r->query('code')))->first();
        abort_unless($c && $c->active && (! $c->expires_at || $c->expires_at->isFuture()) && (! $c->usage_limit || $c->used < $c->usage_limit), 422, 'Invalid or expired coupon.');

        return $c->only('code', 'type', 'value');
    }

    // ---- message log & credits
    public function messageLog(Request $r)
    {
        $this->gate($r);

        return MessageLog::latest('id')->limit(100)->get();
    }

    public function credits(Request $r, Credits $credits)
    {
        return [
            'balance' => $credits->balance($r->user()->tenant_id), 'packs' => config('messaging.packs'), 'low_threshold' => 50,
            'ledger' => CreditTransaction::latest('id')->limit(30)->get(), 'payment_driver' => config('messaging.payment_driver'),
        ];
    }

    public function topup(Request $r, Credits $credits, PaymentGateway $pay): JsonResponse
    {
        abort_unless($r->user()->is_owner, 403, 'Only the owner can buy credits.');
        $pack = collect(config('messaging.packs'))->firstWhere('code', $r->validate(['pack' => ['required', 'string']])['pack']);
        abort_unless($pack, 422, 'Unknown pack.');
        $res = $pay->charge($r->user()->tenant_id, $pack['price_paise'], "{$pack['credits']} message credits");
        abort_unless($res['ok'], 402, 'Payment failed.');
        $after = $credits->add($r->user()->tenant_id, $pack['credits'], 'topup', $res['reference'], $pack['price_paise']);
        Audit::record('credits.topup', null, ['pack' => $pack['code'], 'reference' => $res['reference']]);

        return response()->json(['balance' => $after, 'reference' => $res['reference']], 201);
    }
}
