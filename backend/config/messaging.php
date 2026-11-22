<?php

return [
    // log = local fake (writes to message_log). cloud / msg91 = real providers, to be wired later.
    'drivers' => ['whatsapp' => env('WHATSAPP_DRIVER', 'log'), 'sms' => env('SMS_DRIVER', 'log')],
    'credit_cost' => ['whatsapp' => 1, 'sms' => 1],
    'sync' => env('MESSAGING_SYNC', true),          // run sends inline (no queue worker needed locally)
    'listeners_enabled' => env('MESSAGING_LISTENERS', true),
    'whatsapp' => [
        'app_secret' => env('WHATSAPP_APP_SECRET', 'local-dev-secret'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'local-verify-token'),
        'number' => env('WHATSAPP_NUMBER', '919999900000'),
    ],
    'payment_driver' => env('PAYMENT_DRIVER', 'fake'), // fake | razorpay
    'packs' => [
        ['code' => 'p500', 'credits' => 500, 'price_paise' => 50000],
        ['code' => 'p2000', 'credits' => 2000, 'price_paise' => 180000],
        ['code' => 'p10000', 'credits' => 10000, 'price_paise' => 800000],
    ],
    'ai' => ['driver' => env('AI_DRIVER', 'rules'), 'monthly_quota' => ['free' => 20, 'basic' => 200, 'pro' => 2000], 'cache_seconds' => 600],
    'staff_message_window_seconds' => 900,
];
