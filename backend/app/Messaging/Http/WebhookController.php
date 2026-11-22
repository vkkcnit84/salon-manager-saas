<?php

namespace App\Messaging\Http;

use App\Bot\Services\BotRouter;
use App\Messaging\Models\CampaignMessage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/** Unauthenticated provider callbacks: always verify the signature before touching anything. */
class WebhookController extends Controller
{
    public function verify(Request $r)
    {
        abort_unless($r->query('hub_mode') === 'subscribe' && hash_equals(config('messaging.whatsapp.verify_token'), (string) $r->query('hub_verify_token')), 403);

        return response((string) $r->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function whatsapp(Request $r, BotRouter $bot)
    {
        $secret = (string) config('messaging.whatsapp.app_secret');
        $expected = 'sha256='.hash_hmac('sha256', $r->getContent(), $secret);
        abort_unless($secret !== '' && hash_equals($expected, (string) $r->header('X-Hub-Signature-256')), 403, 'Bad signature.');

        foreach ((array) $r->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $v = $change['value'] ?? [];
                foreach ($v['statuses'] ?? [] as $s) { // delivery receipts: idempotent, only ever moves forward
                    $rank = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];
                    $new = $s['status'] ?? null;
                    if (isset($rank[$new]) && ! empty($s['id'])) {
                        CampaignMessage::withoutGlobalScopes()->where('provider_msg_id', $s['id'])
                            ->whereRaw("case status when 'queued' then 0 when 'sent' then 1 when 'delivered' then 2 when 'read' then 3 else 4 end < ?", [$rank[$new]])
                            ->update(['status' => $new]);
                    }
                }
                foreach ($v['messages'] ?? [] as $m) {
                    $from = $m['from'] ?? '';
                    $button = $m['interactive']['button_reply']['id'] ?? $m['interactive']['list_reply']['id'] ?? null;
                    $text = $m['text']['body'] ?? '';
                    foreach ($bot->handle($from, $text, $button) as $reply) {
                        // Real provider: send $reply back through the gateway here. Local fake already logged it.
                    }
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    public function sms(Request $r)
    {
        abort_unless(hash_equals((string) config('messaging.whatsapp.verify_token'), (string) $r->header('X-Webhook-Token')), 403);
        foreach ((array) $r->input('reports', []) as $rep) {
            if (! empty($rep['id']) && ($rep['status'] ?? '') === 'delivered') {
                CampaignMessage::withoutGlobalScopes()->where('provider_msg_id', $rep['id'])->where('status', 'sent')->update(['status' => 'delivered']);
            }
        }

        return response()->json(['ok' => true]);
    }
}
