<?php

namespace App\Billing\Events;

class InvoiceCreated
{
    public function __construct(public int $invoiceId, public int $tenantId) {}
}
