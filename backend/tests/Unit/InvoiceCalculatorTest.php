<?php

namespace Tests\Unit;

use App\Billing\Services\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    public function test_exclusive_gst_with_flat_bill_discount_split_exactly(): void
    {
        $r = (new InvoiceCalculator)->calculate([
            ['unit' => 50000, 'qty' => 1, 'gst_bps' => 1800],
            ['unit' => 30000, 'qty' => 1, 'gst_bps' => 1800],
        ], 'exclusive', ['type' => 'flat', 'value' => 10000], false);

        $this->assertSame(80000, $r['subtotal']);
        $this->assertSame(10000, $r['discount']);
        $this->assertSame(70000, array_sum(array_column($r['lines'], 'taxable')));
        $this->assertSame(12600, $r['tax']);
        $this->assertSame(82600, $r['total']);
    }

    public function test_inclusive_gst_total_is_unchanged_and_tax_extracted(): void
    {
        $r = (new InvoiceCalculator)->calculate([['unit' => 11800, 'qty' => 1, 'gst_bps' => 1800]], 'inclusive', null, false);
        $this->assertSame(11800, $r['total']);
        $this->assertSame(1800, $r['tax']);
        $this->assertSame(10000, $r['lines'][0]['taxable']);
    }

    public function test_percent_discount_and_rounding_to_rupee(): void
    {
        $r = (new InvoiceCalculator)->calculate([['unit' => 9999, 'qty' => 1, 'gst_bps' => 0]], 'exclusive', ['type' => 'percent', 'value' => 1000], true);
        $this->assertSame(9000, $r['total']);   // 99.99 - 10% = 89.99 -> ₹90
        $this->assertSame(1, $r['rounding']);
    }
}
