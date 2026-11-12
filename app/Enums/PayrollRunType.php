<?php

namespace App\Enums;

/**
 * Payroll run types. Monthly regular runs only (docs/07 D1); corrections after approval are made as one-off
 * adjustments in the next month's run (docs/07 D6).
 */
enum PayrollRunType: string
{
    case Regular = 'regular';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
        };
    }
}
