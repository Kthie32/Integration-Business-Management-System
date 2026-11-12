<?php

namespace App\Enums;

use App\Enums\Contracts\StatusEnum;

/**
 * Payroll run lifecycle (FR-PRL-04): Draft → Calculated → Pending approval → Approved → Paid. A run returned by
 * an approver goes back to Calculated for correction and recalculation. Approved and paid runs are locked.
 */
enum PayrollRunStatus: string implements StatusEnum
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Paid = 'paid';

    public function canTransitionTo(StatusEnum $status): bool
    {
        $allowed = match ($this) {
            self::Draft => [self::Calculated],
            self::Calculated => [self::PendingApproval],
            self::PendingApproval => [self::Calculated, self::Approved],
            self::Approved => [self::Paid],
            self::Paid => [],
        };

        return $status !== $this && in_array($status, $allowed, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Calculated => 'Calculated',
            self::PendingApproval => 'Pending approval',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Calculated => 'info',
            self::PendingApproval => 'warning',
            self::Approved => 'primary',
            self::Paid => 'success',
        };
    }

    /**
     * The run may still be (re)calculated and its adjustments changed.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Calculated], true);
    }

    /**
     * Approved and paid runs are read-only to everyone (FR-PRL-06).
     */
    public function isLocked(): bool
    {
        return in_array($this, [self::Approved, self::Paid], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
