<?php

namespace App\Enums;

/**
 * How a pay component's amount is worked out (FR-PRL-01). Allowances may be fixed or a percentage of basic
 * salary; a percentage of gross pay is only available to deductions, because gross pay includes allowances.
 */
enum PayComponentCalculation: string
{
    case Fixed = 'fixed';
    case PercentOfBasic = 'percent_basic';
    case PercentOfGross = 'percent_gross';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount',
            self::PercentOfBasic => '% of basic salary',
            self::PercentOfGross => '% of gross pay',
        };
    }

    public function isPercentage(): bool
    {
        return $this !== self::Fixed;
    }

    public function allowedFor(PayComponentKind $kind): bool
    {
        return $this !== self::PercentOfGross || $kind === PayComponentKind::Deduction;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $calculation): array => [$calculation->value => $calculation->label()])->all();
    }
}
