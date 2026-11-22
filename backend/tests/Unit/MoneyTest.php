<?php

namespace Tests\Unit;

use App\Shared\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parses_rupees_without_float_error(): void
    {
        $this->assertSame(19950, Money::fromRupees('199.50')->paise);
        $this->assertSame(1999, Money::fromRupees('19.99')->paise);
        $this->assertSame(500, Money::fromRupees('5')->paise);
    }

    public function test_percent_rounds_half_up(): void
    {
        $this->assertSame(1800, Money::paise(10000)->percent(1800)->paise);
        $this->assertSame(2, Money::paise(11)->percent(1800)->paise); // 1.98 -> 2
        $this->assertSame(1, Money::paise(5)->percent(1000)->paise);  // 0.5 -> 1
    }

    public function test_allocate_preserves_total(): void
    {
        $parts = Money::paise(10000)->allocate([1, 1, 1]);
        $this->assertSame(10000, array_sum(array_map(fn ($m) => $m->paise, $parts)));
        $this->assertSame([3334, 3333, 3333], array_map(fn ($m) => $m->paise, $parts));
    }

    public function test_indian_formatting(): void
    {
        $this->assertSame('₹ 1,23,456.00', Money::paise(12345600)->format());
        $this->assertSame('₹ 999.50', Money::paise(99950)->format());
        $this->assertSame('₹ 12,34,567.89', Money::paise(123456789)->format());
    }
}
