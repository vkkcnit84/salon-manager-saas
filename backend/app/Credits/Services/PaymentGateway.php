<?php

namespace App\Credits\Services;

/** Payment provider seam. FakePaymentGateway settles instantly; a Razorpay implementation replaces it later. */
interface PaymentGateway
{
    /** @return array{ok:bool,reference:string} */
    public function charge(int $tenantId, int $amountPaise, string $description): array;
}
