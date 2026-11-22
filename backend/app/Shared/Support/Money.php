<?php

namespace App\Shared\Support;

use InvalidArgumentException;

/** Immutable money in integer paise. No floating-point arithmetic is used for amounts. */
final class Money
{
    private function __construct(public readonly int $paise) {}

    public static function paise(int $paise): self
    {
        return new self($paise);
    }

    /** Parse a decimal rupee string such as "199.50" without going through float. */
    public static function fromRupees(string $rupees): self
    {
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', trim($rupees), $m)) {
            throw new InvalidArgumentException("Invalid rupee amount: {$rupees}");
        }
        $paise = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return new self($m[1] === '-' ? -$paise : $paise);
    }

    public function add(self $o): self
    {
        return new self($this->paise + $o->paise);
    }

    public function sub(self $o): self
    {
        return new self($this->paise - $o->paise);
    }

    /** Percentage with basis-point rate (1850 = 18.50%), rounded half away from zero. */
    public function percent(int $basisPoints): self
    {
        $n = $this->paise * $basisPoints;
        $q = intdiv(abs($n) * 2 + 10000, 20000);

        return new self($n < 0 ? -$q : $q);
    }

    /** Split into parts by integer weights; remainder goes to the first parts so the sum is exact. */
    public function allocate(array $weights): array
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            throw new InvalidArgumentException('Weights must sum to a positive number.');
        }
        $shares = array_map(fn ($w) => intdiv($this->paise * $w, $total), $weights);
        $rem = $this->paise - array_sum($shares);
        for ($i = 0; $rem > 0; $i = ($i + 1) % count($shares), $rem--) {
            $shares[$i]++;
        }

        return array_map(fn ($p) => new self($p), $shares);
    }

    public function equals(self $o): bool
    {
        return $this->paise === $o->paise;
    }

    /** Indian grouping, e.g. ₹ 1,23,456.00 */
    public function format(): string
    {
        $abs = abs($this->paise);
        $int = (string) intdiv($abs, 100);
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        if ($rest !== '') {
            $last3 = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
        }

        return ($this->paise < 0 ? '-' : '').'₹ '.$last3.'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}
