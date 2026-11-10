<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * How often a document number sequence starts again from 1.
 */
enum NumberingReset: string
{
    case Never = 'never';
    case Yearly = 'yearly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Never => 'Never (continuous)',
            self::Yearly => 'Every year',
            self::Monthly => 'Every month',
        };
    }

    /**
     * The sequence period a date falls into: "all", "2026" or "2026-11".
     */
    public function period(CarbonInterface $date): string
    {
        return match ($this) {
            self::Never => 'all',
            self::Yearly => $date->format('Y'),
            self::Monthly => $date->format('Y-m'),
        };
    }
}
