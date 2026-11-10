<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use RoundingMode;
use Stringable;

/**
 * An exact monetary amount held as a decimal string with two decimal places.
 *
 * All arithmetic uses bcmath, so amounts never pass through floating point. Results are rounded half away
 * from zero to two decimal places, matching how amounts are stored (DECIMAL(15,2)).
 */
final class Money implements JsonSerializable, Stringable
{
    public const SCALE = 2;

    /**
     * Internal precision for intermediate results before the final rounding.
     */
    private const WORKING_SCALE = 8;

    private function __construct(private readonly string $amount) {}

    /**
     * Create an amount from an integer or a numeric string (e.g. "1250000", "19.995"). Floats are rejected
     * because they cannot represent most decimal amounts exactly.
     */
    public static function of(int|string|self $amount): self
    {
        if ($amount instanceof self) {
            return $amount;
        }

        return new self(self::round(self::normalise($amount)));
    }

    public static function zero(): self
    {
        return new self('0.00');
    }

    /**
     * Sum a list of amounts.
     *
     * @param  iterable<int|string|Money>  $amounts
     */
    public static function sum(iterable $amounts): self
    {
        $total = self::zero();

        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }

    public function plus(int|string|self $other): self
    {
        return new self(self::round(bcadd($this->amount, self::of($other)->amount, self::WORKING_SCALE)));
    }

    public function minus(int|string|self $other): self
    {
        return new self(self::round(bcsub($this->amount, self::of($other)->amount, self::WORKING_SCALE)));
    }

    /**
     * Multiply by a quantity or factor given as an integer or numeric string (e.g. a quantity of "2.5").
     */
    public function multipliedBy(int|string $factor): self
    {
        return new self(self::round(bcmul($this->amount, self::normalise($factor), self::WORKING_SCALE)));
    }

    /**
     * Divide by a non-zero integer or numeric string.
     */
    public function dividedBy(int|string $divisor): self
    {
        $divisor = self::normalise($divisor);

        if (bccomp($divisor, '0', self::WORKING_SCALE) === 0) {
            throw new InvalidArgumentException('Cannot divide money by zero.');
        }

        return new self(self::round(bcdiv($this->amount, $divisor, self::WORKING_SCALE)));
    }

    /**
     * The given percentage of this amount, e.g. percentage("18") for 18 % VAT.
     */
    public function percentage(int|string $rate): self
    {
        return new self(self::round(bcdiv(bcmul($this->amount, self::normalise($rate), self::WORKING_SCALE), '100', self::WORKING_SCALE)));
    }

    public function negate(): self
    {
        return new self(self::round(bcmul($this->amount, '-1', self::WORKING_SCALE)));
    }

    public function compareTo(int|string|self $other): int
    {
        return bccomp($this->amount, self::of($other)->amount, self::SCALE);
    }

    public function equals(int|string|self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function greaterThan(int|string|self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function lessThan(int|string|self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isZero(): bool
    {
        return $this->compareTo(0) === 0;
    }

    public function isNegative(): bool
    {
        return $this->compareTo(0) < 0;
    }

    public function isPositive(): bool
    {
        return $this->compareTo(0) > 0;
    }

    /**
     * The amount as a two-decimal string, suitable for DECIMAL columns.
     */
    public function toDecimal(): string
    {
        return $this->amount;
    }

    /**
     * Format for display, e.g. "UGX 1,250,000" (0 decimals) or "-USD 12.50".
     */
    public function format(int $decimals = 0, string $symbol = '', string $thousandsSeparator = ',', string $decimalPoint = '.'): string
    {
        $rounded = bcround($this->amount, $decimals, RoundingMode::HalfAwayFromZero);
        $negative = bccomp($rounded, '0', $decimals) < 0;
        $unsigned = ltrim($rounded, '-');

        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $whole = strrev(implode($thousandsSeparator, str_split(strrev($whole), 3)));
        $number = $decimals > 0 ? $whole.$decimalPoint.str_pad(substr($fraction, 0, $decimals), $decimals, '0') : $whole;

        return ($negative ? '-' : '').($symbol !== '' ? $symbol.' ' : '').$number;
    }

    public function jsonSerialize(): string
    {
        return $this->amount;
    }

    public function __toString(): string
    {
        return $this->amount;
    }

    private static function normalise(int|string $value): string
    {
        $value = is_int($value) ? (string) $value : str_replace(',', '', trim($value));

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException("\"{$value}\" is not a valid decimal number.");
        }

        return $value;
    }

    private static function round(string $value): string
    {
        $rounded = bcround($value, self::SCALE, RoundingMode::HalfAwayFromZero);

        return bccomp($rounded, '0', self::SCALE) === 0 ? '0.00' : bcadd($rounded, '0', self::SCALE);
    }
}
