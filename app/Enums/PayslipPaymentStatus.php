<?php

namespace App\Enums;

/**
 * Whether an employee's net pay in a run has been paid out.
 */
enum PayslipPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Not paid yet',
            self::Paid => 'Paid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Paid => 'success',
        };
    }
}
