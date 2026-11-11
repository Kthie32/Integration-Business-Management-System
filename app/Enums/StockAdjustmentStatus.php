<?php

namespace App\Enums;

use App\Enums\Contracts\StatusEnum;

/**
 * Stock adjustments wait for approval by someone other than their creator before stock changes.
 */
enum StockAdjustmentStatus: string implements StatusEnum
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function canTransitionTo(StatusEnum $status): bool
    {
        return $this === self::Pending && in_array($status, [self::Approved, self::Rejected, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'secondary',
        };
    }
}
