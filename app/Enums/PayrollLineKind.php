<?php

namespace App\Enums;

/**
 * Lines of a payslip breakdown (FR-PRL-05).
 */
enum PayrollLineKind: string
{
    case Basic = 'basic';
    case Allowance = 'allowance';
    case Statutory = 'statutory';
    case Deduction = 'deduction';
    case EmployerContribution = 'employer_contribution';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Basic salary',
            self::Allowance => 'Allowance',
            self::Statutory => 'Statutory deduction',
            self::Deduction => 'Deduction',
            self::EmployerContribution => 'Employer contribution',
        };
    }

    public function isEarning(): bool
    {
        return in_array($this, [self::Basic, self::Allowance], true);
    }

    public function isDeduction(): bool
    {
        return in_array($this, [self::Statutory, self::Deduction], true);
    }
}
