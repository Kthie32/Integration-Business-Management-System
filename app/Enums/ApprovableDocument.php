<?php

namespace App\Enums;

/**
 * Document types that run through the approval engine and can have configured workflows. Payroll runs join
 * in Phase 12.
 */
enum ApprovableDocument: string
{
    case Requisition = 'requisition';

    public function label(): string
    {
        return match ($this) {
            self::Requisition => 'Requisition',
        };
    }

    /**
     * The permission an "any authorised approver" step requires.
     */
    public function approvePermission(): string
    {
        return match ($this) {
            self::Requisition => 'requisitions.approve',
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
