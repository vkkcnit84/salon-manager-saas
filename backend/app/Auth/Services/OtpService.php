<?php

namespace App\Auth\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    private const TTL_MINUTES = 5;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private SmsGateway $sms) {}

    public function send(string $mobile): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('otp_codes')->where('mobile', $mobile)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        DB::table('otp_codes')->insert([
            'mobile' => $mobile,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->sms->send($mobile, "Your Salon Manager login code is {$code}. Valid for ".self::TTL_MINUTES.' minutes.');
    }

    public function verify(string $mobile, string $code): bool
    {
        $row = DB::table('otp_codes')->where('mobile', $mobile)->whereNull('consumed_at')
            ->where('expires_at', '>', now())->latest('id')->first();
        if (! $row || $row->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }
        if (! Hash::check($code, $row->code_hash)) {
            DB::table('otp_codes')->where('id', $row->id)->increment('attempts');

            return false;
        }
        DB::table('otp_codes')->where('id', $row->id)->update(['consumed_at' => now()]);

        return true;
    }
}
