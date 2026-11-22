<?php

namespace App\Mobile;

use App\Mobile\Models\DeviceToken;
use Illuminate\Support\Facades\Log;

/** TODO (later): POST https://fcm.googleapis.com/v1/projects/{id}/messages:send with an OAuth2 service-account token. Local fake logs only. */
class FcmSender
{
    public function toUser(int $userId, string $title, string $body): int
    {
        $tokens = DeviceToken::where('user_id', $userId)->pluck('token');
        foreach ($tokens as $t) {
            Log::info('FCM (fake)', ['to' => substr($t, 0, 12).'…', 'title' => $title, 'body' => $body]);
        }

        return $tokens->count();
    }
}
