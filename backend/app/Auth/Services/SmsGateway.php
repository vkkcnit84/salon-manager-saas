<?php

namespace App\Auth\Services;

interface SmsGateway
{
    public function send(string $mobile, string $message): void;
}
