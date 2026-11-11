<?php

namespace App\Enums;

use App\Enums\Contracts\StatusEnum;

/**
 * A payment is recorded as completed and can only be reversed (never edited or deleted).
 */
enum PaymentStatus: string implements StatusEnum
{
    case Completed = 'completed';
    case Reversed = 'reversed';

    public function canTransitionTo(StatusEnum $status): bool
    {
        return $this === self::Completed && $status === self::Reversed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::Reversed => 'Reversed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Reversed => 'danger',
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
