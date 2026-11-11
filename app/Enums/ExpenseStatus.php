<?php

namespace App\Enums;

use App\Enums\Contracts\StatusEnum;

/**
 * Expense lifecycle. A draft can be edited or deleted; recording it gives it a number and locks it, and a
 * recorded expense is voided with a reason, never deleted (FR-EXP-03). The Phase 11 approval engine can
 * place an approval step between draft and recorded without changing recorded or void expenses.
 */
enum ExpenseStatus: string implements StatusEnum
{
    case Draft = 'draft';
    case Recorded = 'recorded';
    case Void = 'void';

    public function canTransitionTo(StatusEnum $status): bool
    {
        return match ($this) {
            self::Draft => $status === self::Recorded,
            self::Recorded => $status === self::Void,
            self::Void => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Recorded => 'Recorded',
            self::Void => 'Void',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Recorded => 'success',
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
