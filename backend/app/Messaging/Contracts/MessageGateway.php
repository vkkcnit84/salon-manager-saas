<?php

namespace App\Messaging\Contracts;

use App\Messaging\Models\MessageTemplate;

interface MessageGateway
{
    public function sendTemplate(string $to, MessageTemplate $template, array $variables, array $buttons = []): SendResult;

    public function sendText(string $to, string $text, array $buttons = []): SendResult;
}
