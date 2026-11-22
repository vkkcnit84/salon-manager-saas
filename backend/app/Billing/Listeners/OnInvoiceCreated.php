<?php

namespace App\Billing\Listeners;

use App\Billing\Events\InvoiceCreated;
use App\Billing\Models\Invoice;
use App\Bot\Services\StaffNotifier;
use App\Messaging\Models\Automation;
use App\Messaging\Models\MessageTemplate;
use App\Messaging\Services\MessageSender;
use App\Shops\Models\Shop;

/** Thank-you/bill message to the customer (automation) and an earnings ping to each staff on the bill. */
class OnInvoiceCreated
{
    public function __construct(private MessageSender $sender, private StaffNotifier $staff) {}

    public function handle(InvoiceCreated $e): void
    {
        if (! config('messaging.listeners_enabled')) {
            return;
        }
        // Production: MESSAGING_SYNC=false pushes provider calls onto the queue so bill saving never waits on WhatsApp.
        if (! config('messaging.sync')) {
            \App\Billing\Jobs\SendInvoiceMessagesJob::dispatch($e->invoiceId, $e->tenantId);

            return;
        }
        $this->run($e->invoiceId, $e->tenantId);
    }

    /** Binds the tenant from the event (not from the request), so it is also correct on a queue worker. */
    public function run(int $invoiceId, int $tenantId): void
    {
        $ctx = app(\App\Tenancy\Support\TenantContext::class);
        [$prevTenant, $prevShop] = [$ctx->tenantId(), $ctx->shopId()];
        $ctx->set($tenantId, $prevShop);
        try {
            $this->deliver($invoiceId);
        } finally {
            $prevTenant === null ? $ctx->clear() : $ctx->set($prevTenant, $prevShop);
        }
    }

    private function deliver(int $invoiceId): void
    {
        try {
            $invoice = Invoice::with('customer', 'items')->find($invoiceId);
            if (! $invoice) {
                return;
            }
            $a = Automation::where('trigger', 'invoice_created')->where('active', true)->first();
            if ($a && $invoice->customer && ($tpl = MessageTemplate::find($a->template_id))) {
                $shop = Shop::find($invoice->shop_id)?->name ?? '';
                // delay_hours would become ->delay() on a queued listener once a worker runs; sends immediately locally.
                $this->sender->toCustomer($invoice->customer, $tpl, [$invoice->customer->name, $shop, $invoice->number, '₹'.number_format($invoice->total_paise / 100, 2)], 'automation:invoice_created');
            }
            $this->staff->afterInvoice($invoice);
        } catch (\Throwable $ex) {
            report($ex); // messaging must never break billing
        }
    }
}
