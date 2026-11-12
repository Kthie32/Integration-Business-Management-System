<?php

namespace App\Enums;

use App\Enums\Contracts\StatusEnum;

/**
 * Requisition lifecycle (FR-REQ-02). A returned requisition is back with its requester for revision and is
 * resubmitted into a fresh approval round.
 */
enum RequisitionStatus: string implements StatusEnum
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InApproval = 'in_approval';
    case Returned = 'returned';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function canTransitionTo(StatusEnum $status): bool
    {
        $allowed = match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted, self::InApproval => [self::InApproval, self::Approved, self::Rejected, self::Returned, self::Cancelled],
            self::Returned => [self::Submitted, self::Cancelled],
            self::Approved => [self::PartiallyFulfilled, self::Fulfilled],
            self::PartiallyFulfilled => [self::Fulfilled],
            self::Rejected, self::Fulfilled, self::Cancelled => [],
        };

        return $status !== $this && in_array($status, $allowed, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::InApproval => 'In approval',
            self::Returned => 'Returned for revision',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::PartiallyFulfilled => 'Partially fulfilled',
            self::Fulfilled => 'Fulfilled',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'secondary',
            self::Submitted, self::InApproval => 'warning',
            self::Returned, self::PartiallyFulfilled => 'info',
            self::Approved => 'primary',
            self::Rejected => 'danger',
            self::Fulfilled => 'success',
        };
    }

    /**
     * The requester may still change the requisition.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    /**
     * Waiting for an approval decision.
     */
    public function isAwaitingApproval(): bool
    {
        return in_array($this, self::pending(), true);
    }

    /**
     * Approved lines may be issued or purchased.
     */
    public function isFulfillable(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyFulfilled], true);
    }

    /**
     * @return list<self>
     */
    public static function pending(): array
    {
        return [self::Submitted, self::InApproval];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
