<?php

namespace App\Enums;

/**
 * Document types that run through the approval engine and can have configured workflows.
 */
enum ApprovableDocument: string
{
    case Requisition = 'requisition';
    case PayrollRun = 'payroll_run';

    public function label(): string
    {
        return match ($this) {
            self::Requisition => 'Requisition',
            self::PayrollRun => 'Payroll run',
        };
    }

    /**
     * The permission an "any authorised approver" step requires.
     */
    public function approvePermission(): string
    {
        return match ($this) {
            self::Requisition => 'requisitions.approve',
            self::PayrollRun => 'payroll.approve',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
