<?php

namespace App\Bot\Services;

use App\Credits\Services\Credits;
use App\Messaging\Models\MessageLog;
use App\Messaging\Models\MessageTemplate;
use App\Messaging\Services\GatewayManager;

/**
 * Business-initiated WhatsApp to a team member. Costs a credit. Free text is only legal inside the
 * 24h window after their last inbound message; outside it we must use an approved utility template.
 */
class Pusher
{
    public const TEMPLATES = ['summary' => 'owner_daily_summary', 'alert' => 'owner_alert'];

    public function __construct(private GatewayManager $gateways, private Credits $credits) {}

    public function windowOpen(string $mobile): bool
    {
        return MessageLog::where('to', $mobile)->where('direction', 'in')->where('created_at', '>', now()->subHours(24))->exists();
    }

    public function push(int $tenantId, string $mobile, string $text, string $kind, array $buttons = []): array
    {
        if (! $this->credits->debit($tenantId, 1, "bot:{$kind}")) {
            return ['ok' => false, 'error' => 'Insufficient credits'];
        }
        $gw = $this->gateways->for('whatsapp');
        $r = $this->windowOpen($mobile)
            ? $gw->sendText($mobile, $text, $buttons)
            : $gw->sendTemplate($mobile, new MessageTemplate(['name' => self::TEMPLATES[$kind] ?? 'owner_alert', 'channel' => 'whatsapp', 'category' => 'utility', 'body' => '{{1}}']), [$text], $buttons);
        if (! $r->ok) {
            $this->credits->add($tenantId, 1, 'refund', "bot:{$kind}:failed");
        }

        return ['ok' => $r->ok, 'error' => $r->error];
    }
}
