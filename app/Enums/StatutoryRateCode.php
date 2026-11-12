<?php

namespace App\Enums;

/**
 * Statutory rule sets stored as effective-dated configuration (FR-PRL-03), never hard-coded in the calculator.
 */
enum StatutoryRateCode: string
{
    case PayeResident = 'PAYE_RESIDENT';
    case PayeNonResident = 'PAYE_NON_RESIDENT';
    case Nssf = 'NSSF';
    case Lst = 'LST';

    public function label(): string
    {
        return match ($this) {
            self::PayeResident => 'PAYE – resident employees',
            self::PayeNonResident => 'PAYE – non-resident employees',
            self::Nssf => 'NSSF contributions',
            self::Lst => 'Local Service Tax (LST)',
        };
    }

    public function isPaye(): bool
    {
        return in_array($this, [self::PayeResident, self::PayeNonResident], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $code): array => [$code->value => $code->label()])->all();
    }
}
