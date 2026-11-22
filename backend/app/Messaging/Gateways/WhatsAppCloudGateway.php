<?php

namespace App\Messaging\Gateways;

use App\Messaging\Contracts\MessageGateway;
use App\Messaging\Contracts\SendResult;
use App\Messaging\Models\MessageTemplate;

/**
 * TODO (later): Meta Graph API. POST https://graph.facebook.com/v20.0/{phone_number_id}/messages
 * with the tenant's (encrypted) token, or the platform's shared number. Not wired yet.
 */
class WhatsAppCloudGateway implements MessageGateway
{
    public function sendTemplate(string $to, MessageTemplate $template, array $variables, array $buttons = []): SendResult
    {
        throw new \RuntimeException('WhatsApp Cloud gateway is not configured yet. Set WHATSAPP_DRIVER=log.');
    }

    public function sendText(string $to, string $text, array $buttons = []): SendResult
    {
        throw new \RuntimeException('WhatsApp Cloud gateway is not configured yet. Set WHATSAPP_DRIVER=log.');
    }
}
