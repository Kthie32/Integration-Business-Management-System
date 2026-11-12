<?php

namespace App\Enums;

/**
 * Whether a pay component adds to pay or is taken from it (FR-PRL-01).
 */
enum PayComponentKind: string
{
    case Allowance = 'allowance';
    case Deduction = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::Allowance => 'Allowance',
            self::Deduction => 'Deduction',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $kind): array => [$kind->value => $kind->label()])->all();
    }
}
