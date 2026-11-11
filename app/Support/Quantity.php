<?php

namespace App\Support;

/**
 * Displays stock quantities (stored with three decimals) without trailing zeros: "1,250", "2.5", "-0.125".
 */
final class Quantity
{
    public static function format(string|int|float|null $quantity): string
    {
        $value = (string) ($quantity ?? '0');
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = number_format((int) $whole);
        $fraction = rtrim($fraction, '0');

        $formatted = $fraction === '' ? $whole : "{$whole}.{$fraction}";

        return ($negative && $formatted !== '0' ? '-' : '').$formatted;
    }
}
