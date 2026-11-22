<?php

namespace App\Credits\Services;

class FakePaymentGateway implements PaymentGateway
{
    public function charge(int $tenantId, int $amountPaise, string $description): array
    {
        return ['ok' => true, 'reference' => 'FAKE-PAY-'.strtoupper(bin2hex(random_bytes(4)))];
    }
}
