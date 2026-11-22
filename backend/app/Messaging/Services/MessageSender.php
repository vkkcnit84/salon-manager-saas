<?php

namespace App\Messaging\Services;

use App\Credits\Services\Credits;
use App\Customers\Models\Customer;
use App\Messaging\Models\MessageTemplate;

/**
 * The single path for sending to a customer. Enforces consent/opt-out, debits credits, calls the gateway.
 * Failed sends are refunded because providers do not bill undelivered messages.
 */
class MessageSender
{
    public function __construct(private GatewayManager $gateways, private Credits $credits) {}

    /** @return array{ok:bool,id:?string,error:?string} */
    public function toCustomer(Customer $c, MessageTemplate $t, array $vars, string $reference): array
    {
        $channel = $t->channel;
        $allowed = $c->opted_out_at === null && ($channel === 'sms' ? $c->consent_sms : $c->consent_whatsapp);
        if (! $allowed) {
            return ['ok' => false, 'id' => null, 'error' => 'No consent / opted out'];
        }
        $cost = (int) config("messaging.credit_cost.{$channel}", 1);
        if (! $this->credits->debit($c->tenant_id, $cost, $reference)) {
            return ['ok' => false, 'id' => null, 'error' => 'Insufficient credits'];
        }
        try {
            $r = $this->gateways->for($channel)->sendTemplate($c->mobile, $t, $vars);
        } catch (\Throwable $e) {
            $r = new \App\Messaging\Contracts\SendResult(false, null, $e->getMessage());
        }
        if (! $r->ok) {
            $this->credits->add($c->tenant_id, $cost, 'refund', $reference.':failed');
        }

        return ['ok' => $r->ok, 'id' => $r->id, 'error' => $r->error];
    }

    /** Business-initiated text to a team member (bot). Costs a credit; skipped if none left. */
    public function toTeamMember(int $tenantId, string $mobile, string $text, array $buttons, string $reference): bool
    {
        if (! $this->credits->debit($tenantId, 1, $reference)) {
            return false;
        }
        $r = $this->gateways->for('whatsapp')->sendText($mobile, $text, $buttons);
        if (! $r->ok) {
            $this->credits->add($tenantId, 1, 'refund', $reference.':failed');
        }

        return $r->ok;
    }
}
