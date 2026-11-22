<?php

namespace App\Bot\Http;

use App\Bot\Models\WaitingEntry;
use App\Bot\Services\BotRouter;
use App\Bot\Services\OwnerDigest;
use App\Bot\Services\Pusher;
use App\Customers\Models\Customer;
use App\Messaging\Models\MessageLog;
use App\Models\User;
use App\Shops\Models\Shop;
use App\Staff\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BotController extends Controller
{
    /** Local WhatsApp simulator: lets the owner chat as any team member/staff/customer. */
    public function identities(Request $r)
    {
        abort_unless($r->user()->is_owner, 403);
        $t = $r->user()->tenant_id;
        $users = User::where('tenant_id', $t)->whereNotNull('mobile')->get(['name', 'mobile'])->map(fn ($u) => ['label' => "{$u->name} (team)", 'mobile' => $u->mobile]);
        $staff = Staff::whereNotNull('mobile')->get(['name', 'mobile'])->map(fn ($s) => ['label' => "{$s->name} (staff)", 'mobile' => $s->mobile]);
        $customers = Customer::orderBy('name')->limit(6)->get(['name', 'mobile'])->map(fn ($c) => ['label' => "{$c->name} (customer)", 'mobile' => $c->mobile]);

        return ['identities' => $users->concat($staff)->concat($customers)->values(), 'checkin_codes' => Shop::all(['name', 'code'])->map(fn ($s) => ['shop' => $s->name, 'text' => 'CHECKIN-'.$s->code]), 'whatsapp_number' => config('messaging.whatsapp.number')];
    }

    public function simulate(Request $r, BotRouter $bot)
    {
        abort_unless($r->user()->is_owner, 403);
        $d = $r->validate(['from' => ['required', 'string', 'max:15'], 'text' => ['sometimes', 'nullable', 'string', 'max:500'], 'button' => ['sometimes', 'nullable', 'string', 'max:80']]);
        $replies = $bot->handle($d['from'], (string) ($d['text'] ?? ''), $d['button'] ?? null, $r->user()->tenant_id);
        app(\App\Tenancy\Support\TenantContext::class)->set($r->user()->tenant_id);

        return ['replies' => $replies];
    }

    public function prefs(Request $r)
    {
        $d = $r->validate(['nightly' => ['required', 'boolean'], 'nightly_time' => ['sometimes', 'regex:/^([01]\d|2[0-3]):(00|30)$/']]);
        abort_unless($r->user()->mobile, 422, 'Add a mobile number to your profile to receive WhatsApp updates.');
        $r->user()->forceFill(['bot_prefs' => $d + ($r->user()->bot_prefs ?? [])])->save();

        return $r->user()->fresh()->bot_prefs;
    }

    /** "Send this on WhatsApp" button on the dashboard. */
    public function sendSummary(Request $r, OwnerDigest $digest, Pusher $pusher)
    {
        abort_unless($r->user()->is_owner, 403);
        abort_unless($r->user()->mobile, 422, 'Add your mobile number first.');
        $res = $pusher->push($r->user()->tenant_id, $r->user()->mobile, $digest->summaryText($r->user()->tenant_id), 'summary');
        abort_unless($res['ok'], 402, $res['error'] ?? 'Could not send.');

        return ['message' => 'Summary sent to '.$r->user()->mobile.' (local log)'];
    }

    public function waiting(Request $r)
    {
        $shop = $r->attributes->get('shop');

        return WaitingEntry::with('customer:id,name,mobile')->where('shop_id', $shop->id)->where('status', 'waiting')->orderBy('id')->get();
    }

    public function serve(Request $r, int $id)
    {
        $e = WaitingEntry::findOrFail($id);
        $e->update(['status' => $r->input('status') === 'left' ? 'left' : 'served']);

        return $e;
    }
}
