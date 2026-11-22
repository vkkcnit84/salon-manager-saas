<?php

namespace App\Billing\Jobs;

use App\Billing\Listeners\OnInvoiceCreated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Queued thank-you + staff messages. IDs only in the payload; the tenant is bound inside OnInvoiceCreated::run(). */
class SendInvoiceMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $invoiceId, public int $tenantId) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(OnInvoiceCreated $listener): void
    {
        $listener->run($this->invoiceId, $this->tenantId);
    }
}
