<?php

namespace App\Auth\Services;

use Illuminate\Support\Facades\Log;

/** Dev/test gateway. Replaced by an MSG91/Textlocal implementation (DLT template) in Sprint 5. */
class LogSmsGateway implements SmsGateway
{
    public function send(string $mobile, string $message): void
    {
        Log::info('SMS', ['mobile' => $mobile, 'message' => $message]);
    }
}
