<?php

namespace App\Messaging\Services;

use App\Messaging\Contracts\MessageGateway;
use App\Messaging\Gateways\LogGateway;
use App\Messaging\Gateways\Msg91SmsGateway;
use App\Messaging\Gateways\WhatsAppCloudGateway;

class GatewayManager
{
    public function for(string $channel): MessageGateway
    {
        return match (config("messaging.drivers.{$channel}", 'log')) {
            'cloud' => new WhatsAppCloudGateway,
            'msg91' => new Msg91SmsGateway,
            default => new LogGateway($channel),
        };
    }
}
