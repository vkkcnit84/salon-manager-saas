<?php

namespace App\Messaging\Gateways;

use App\Messaging\Contracts\MessageGateway;
use App\Messaging\Contracts\SendResult;
use App\Messaging\Models\MessageTemplate;

/** TODO (later): MSG91 flow API with DLT template id and sender id. Not wired yet. */
class Msg91SmsGateway implements MessageGateway
{
    public function sendTemplate(string $to, MessageTemplate $template, array $variables, array $buttons = []): SendResult
    {
        throw new \RuntimeException('MSG91 gateway is not configured yet. Set SMS_DRIVER=log.');
    }

    public function sendText(string $to, string $text, array $buttons = []): SendResult
    {
        throw new \RuntimeException('MSG91 gateway is not configured yet. Set SMS_DRIVER=log.');
    }
}
