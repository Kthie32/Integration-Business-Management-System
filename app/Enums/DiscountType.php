<?php

namespace App\Enums;

/**
 * How a document-level discount is expressed.
 */
enum DiscountType: string
{
    case Percentage = 'percentage';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage (%)',
            self::Amount => 'Fixed amount',
        };
    }
}
