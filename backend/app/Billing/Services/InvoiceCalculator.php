<?php

namespace App\Billing\Services;

use App\Shared\Support\Money;

/**
 * Pure totals engine, all in integer paise.
 * lines: [['unit'=>int,'qty'=>int,'discount'=>int,'gst_bps'=>int], ...]
 * bill discount: ['type'=>'flat'|'percent','value'=>int] (percent in basis points), split across lines by value.
 */
class InvoiceCalculator
{
    public function calculate(array $lines, string $taxMode, ?array $billDiscount, bool $roundToRupee = true, int $tip = 0): array
    {
        $gross = array_map(fn ($l) => max(0, $l['unit'] * $l['qty'] - ($l['discount'] ?? 0)), $lines);
        $sum = array_sum($gross);

        $billOff = 0;
        if ($billDiscount && $sum > 0) {
            $billOff = $billDiscount['type'] === 'percent'
                ? Money::paise($sum)->percent($billDiscount['value'])->paise
                : $billDiscount['value'];
            $billOff = min($billOff, $sum);
        }
        $shares = $billOff > 0 && $sum > 0
            ? array_map(fn ($m) => $m->paise, Money::paise($billOff)->allocate($gross))
            : array_fill(0, count($gross), 0);

        $out = [];
        $taxTotal = 0;
        $subtotal = 0;
        $payable = 0;
        foreach ($lines as $i => $l) {
            $net = $gross[$i] - $shares[$i];
            if ($taxMode === 'inclusive') {
                $taxable = intdiv($net * 10000 * 2 + (10000 + $l['gst_bps']), 2 * (10000 + $l['gst_bps']));
                $tax = $net - $taxable;
                $total = $net;
            } else {
                $taxable = $net;
                $tax = Money::paise($net)->percent($l['gst_bps'])->paise;
                $total = $net + $tax;
            }
            $out[] = ['taxable' => $taxable, 'tax' => $tax, 'total' => $total, 'discount' => ($l['discount'] ?? 0) + $shares[$i]];
            $taxTotal += $tax;
            $subtotal += $l['unit'] * $l['qty'];
            $payable += $total;
        }

        $exact = $payable + $tip;
        $rounded = $roundToRupee ? intdiv($exact + 50, 100) * 100 : $exact;

        return [
            'lines' => $out,
            'subtotal' => $subtotal,
            'discount' => array_sum(array_column($out, 'discount')),
            'tax' => $taxTotal,
            'tip' => $tip,
            'rounding' => $rounded - $exact,
            'total' => $rounded,
        ];
    }
}
