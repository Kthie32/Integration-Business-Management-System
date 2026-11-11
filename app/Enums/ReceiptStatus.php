<?php

namespace App\Enums;

use App\Enums\Contracts\StatusEnum;

/**
 * A receipt is valid from the moment its payment is recorded. Reversing the payment voids the receipt;
 * it is never edited or deleted.
 */
enum ReceiptStatus: string implements StatusEnum
{
    case Valid = 'valid';
    case Void = 'void';

    public function canTransitionTo(StatusEnum $status): bool
    {
        return $this === self::Valid && $status === self::Void;
    }

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::Void => 'Void',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::Void => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
