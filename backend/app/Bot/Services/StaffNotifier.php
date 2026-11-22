<?php

namespace App\Bot\Services;

use App\Billing\Models\Invoice;
use App\Messaging\Services\MessageSender;
use App\Shared\Support\Money;
use App\Staff\Models\Staff;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StaffNotifier
{
    public function __construct(private MessageSender $sender) {}

    /** At most one message per staff per 15 minutes, to save credits. */
    public function afterInvoice(Invoice $invoice): void
    {
        foreach ($invoice->items->whereNotNull('staff_id')->groupBy('staff_id') as $staffId => $items) {
            $staff = Staff::find($staffId);
            if (! $staff || ! $staff->bot_enabled || ! $staff->mobile) {
                continue;
            }
            if (! Cache::add("bot:staff:{$staffId}", 1, (int) config('messaging.staff_message_window_seconds'))) {
                continue;
            }
            $today = DB::table('invoice_items as i')->join('invoices as v', 'v.id', '=', 'i.invoice_id')->where('i.staff_id', $staffId)->where('v.status', '!=', 'void')
                ->whereBetween('v.created_at', [now('Asia/Kolkata')->startOfDay()->utc(), now('Asia/Kolkata')->endOfDay()->utc()])
                ->selectRaw('coalesce(sum(i.qty),0) n, coalesce(sum(i.commission_paise),0) c')->first();
            $first = $items->first();
            $text = "✂️ {$first->name} — ".Money::paise($items->sum('total_paise'))->format()." (bill {$invoice->number}).\nToday: {$today->n} services · commission ".Money::paise((int) $today->c)->format().'.';
            $this->sender->toTeamMember($invoice->tenant_id, $staff->mobile, $text, [], 'bot:staff');
        }
    }
}
