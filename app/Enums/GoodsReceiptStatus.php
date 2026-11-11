<?php

namespace App\Enums;

use App\Enums\Contracts\StatusEnum;

/**
 * A goods received note is prepared as a draft, posted into stock, and can be voided (which reverses its stock).
 */
enum GoodsReceiptStatus: string implements StatusEnum
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Void = 'void';

    public function canTransitionTo(StatusEnum $status): bool
    {
        return match ($this) {
            self::Draft => $status === self::Posted,
            self::Posted => $status === self::Void,
            self::Void => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
            self::Void => 'Void',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Posted => 'success',
            self::Void => 'danger',
        };
    }
}
