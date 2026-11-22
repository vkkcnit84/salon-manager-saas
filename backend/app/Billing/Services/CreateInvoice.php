<?php

namespace App\Billing\Services;

use App\Billing\Models\Invoice;
use App\Billing\Models\InvoiceItem;
use App\Billing\Models\Payment;
use App\Catalog\Models\Product;
use App\Catalog\Models\Service;
use App\Catalog\Models\ShopServicePrice;
use App\Shared\Support\Money;
use App\Shops\Models\Shop;
use App\Staff\Models\Staff;
use App\Staff\Services\CommissionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One transaction: price lookup, totals, sequential number, items+commission snapshot, payments. */
class CreateInvoice
{
    public function __construct(private InvoiceCalculator $calc, private CommissionResolver $commission) {}

    public function __invoke(Shop $shop, int $userId, array $data): Invoice
    {
        if (! empty($data['idempotency_key'])) {
            $existing = Invoice::where('shop_id', $shop->id)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing->load('items', 'payments');
            }
        }

        $invoice = DB::transaction(function () use ($shop, $userId, $data) {
            $resolved = [];
            foreach ($data['items'] as $i) {
                $isService = $i['type'] === 'service';
                $model = $isService ? Service::findOrFail($i['id']) : Product::findOrFail($i['id']);
                $unit = $isService
                    ? (ShopServicePrice::where('shop_id', $shop->id)->where('service_id', $model->id)->value('price_paise') ?? $model->price_paise)
                    : $model->price_paise;
                $resolved[] = ['in' => $i, 'model' => $model, 'unit' => (int) $unit, 'qty' => (int) ($i['qty'] ?? 1), 'discount' => (int) ($i['discount'] ?? 0), 'gst_bps' => (int) $model->gst_bps];
            }

            $settings = $shop->settings ?? [];
            $calc = $this->calc->calculate($resolved, $shop->tax_mode, $data['discount'] ?? null, ($settings['rounding'] ?? 'nearest_rupee') !== 'none', (int) ($data['tip'] ?? 0));

            if (! empty($data['pay_in_full'])) {
                $data['payments'] = [['method' => $data['pay_in_full'], 'amount' => $calc['total']]];
            }
            $paid = array_sum(array_column($data['payments'] ?? [], 'amount'));
            if ($paid > $calc['total']) {
                throw ValidationException::withMessages(['payments' => 'Payments exceed the bill total.']);
            }
            if ($paid < $calc['total'] && empty($data['customer_id'])) {
                throw ValidationException::withMessages(['customer_id' => 'A customer is required to leave a balance due.']);
            }

            [$fy, $seq] = $this->nextNumber($shop);
            $invoice = Invoice::create([
                'tenant_id' => $shop->tenant_id,
                'shop_id' => $shop->id,
                'customer_id' => $data['customer_id'] ?? null,
                'created_by' => $userId,
                'financial_year' => $fy,
                'seq' => $seq,
                'number' => ($shop->invoice_prefix ?: 'INV').'/'.$fy.'/'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                'status' => $paid < $calc['total'] ? 'due' : 'paid',
                'tax_mode' => $shop->tax_mode,
                'subtotal_paise' => $calc['subtotal'],
                'discount_paise' => $calc['discount'],
                'tax_paise' => $calc['tax'],
                'tip_paise' => $calc['tip'],
                'rounding_paise' => $calc['rounding'],
                'total_paise' => $calc['total'],
                'paid_paise' => $paid,
                'due_paise' => $calc['total'] - $paid,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($resolved as $n => $r) {
                $line = $calc['lines'][$n];
                $staffId = $r['in']['staff_id'] ?? null;
                $commissionPaise = 0;
                $snapshot = null;
                if ($staffId) {
                    $staff = Staff::findOrFail($staffId);
                    $rule = $this->commission->rule($staff, $r['model'] instanceof Service ? $r['model'] : null);
                    $commissionPaise = $this->commission->amount($rule, Money::paise($line['taxable']), $r['qty'])->paise;
                    $snapshot = $rule + ['base_paise' => $line['taxable']];
                }
                InvoiceItem::create([
                    'tenant_id' => $shop->tenant_id,
                    'invoice_id' => $invoice->id,
                    'item_type' => $r['in']['type'],
                    'item_id' => $r['model']->id,
                    'name' => $r['model']->name,
                    'qty' => $r['qty'],
                    'unit_price_paise' => $r['unit'],
                    'discount_paise' => $line['discount'],
                    'gst_bps' => $r['gst_bps'],
                    'taxable_paise' => $line['taxable'],
                    'tax_paise' => $line['tax'],
                    'total_paise' => $line['total'],
                    'staff_id' => $staffId,
                    'commission_paise' => $commissionPaise,
                    'commission_snapshot' => $snapshot,
                ]);
                if ($r['model'] instanceof Product) {
                    $r['model']->decrement('stock', $r['qty']);
                }
            }

            foreach ($data['payments'] ?? [] as $p) {
                Payment::create(['tenant_id' => $shop->tenant_id, 'invoice_id' => $invoice->id, 'method' => $p['method'], 'amount_paise' => (int) $p['amount']]);
            }

            return $invoice->load('items', 'payments');
        });

        event(new \App\Billing\Events\InvoiceCreated($invoice->id, $shop->tenant_id));

        return $invoice;
    }

    /** Indian financial year (Apr–Mar); counter row is locked so parallel saves never collide. */
    private function nextNumber(Shop $shop): array
    {
        $now = now($shop->timezone);
        $start = $now->month >= 4 ? $now->year : $now->year - 1;
        $fy = $start.'-'.substr((string) ($start + 1), 2);

        DB::table('shop_counters')->insertOrIgnore([
            'tenant_id' => $shop->tenant_id, 'shop_id' => $shop->id, 'financial_year' => $fy,
            'last_invoice_no' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = DB::table('shop_counters')->where('shop_id', $shop->id)->where('financial_year', $fy)->lockForUpdate()->first();
        $seq = $row->last_invoice_no + 1;
        DB::table('shop_counters')->where('id', $row->id)->update(['last_invoice_no' => $seq, 'updated_at' => now()]);

        return [$fy, $seq];
    }
}
