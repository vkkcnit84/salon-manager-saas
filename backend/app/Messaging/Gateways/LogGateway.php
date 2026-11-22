<?php

namespace App\Messaging\Gateways;

use App\Messaging\Contracts\MessageGateway;
use App\Messaging\Contracts\SendResult;
use App\Messaging\Models\MessageLog;
use App\Messaging\Models\MessageTemplate;
use App\Messaging\Services\TemplateRenderer;
use App\Tenancy\Support\TenantContext;

/** Local fake: nothing leaves the machine; every message is recorded in message_log. */
class LogGateway implements MessageGateway
{
    public function __construct(private string $channel) {}

    public function sendTemplate(string $to, MessageTemplate $template, array $variables, array $buttons = []): SendResult
    {
        return $this->record($to, TemplateRenderer::render($template->body, $variables), $buttons, $template->name);
    }

    public function sendText(string $to, string $text, array $buttons = []): SendResult
    {
        return $this->record($to, $text, $buttons, null);
    }

    private function record(string $to, string $body, array $buttons, ?string $template): SendResult
    {
        if (! preg_match('/^\+?\d{10,15}$/', $to)) {
            return new SendResult(false, null, 'Invalid number');
        }
        $id = 'log_'.bin2hex(random_bytes(6));
        MessageLog::create([
            'tenant_id' => app(TenantContext::class)->tenantId(), 'channel' => $this->channel, 'direction' => 'out', 'to' => $to,
            'template' => $template, 'body' => $body, 'buttons' => $buttons ?: null, 'status' => 'sent', 'provider_msg_id' => $id,
        ]);

        return new SendResult(true, $id);
    }
}
