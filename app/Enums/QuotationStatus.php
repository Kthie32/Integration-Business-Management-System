<?php

namespace App\Enums;

use App\Enums\Contracts\StatusEnum;

/**
 * Quotation lifecycle. A draft is submitted and, when its total reaches the approval threshold, waits for
 * approval by someone other than its author. Approved quotations are sent to the customer, who accepts or
 * rejects them. Changing a finalised quotation creates a revision that supersedes it.
 */
enum QuotationStatus: string implements StatusEnum
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Superseded = 'superseded';

    public function canTransitionTo(StatusEnum $status): bool
    {
        $allowed = match ($this) {
            self::Draft => [self::PendingApproval, self::Approved, self::Cancelled],
            self::PendingApproval => [self::Approved, self::Draft, self::Cancelled],
            self::Approved => [self::Sent, self::Accepted, self::Rejected, self::Expired, self::Cancelled, self::Superseded],
            self::Sent => [self::Accepted, self::Rejected, self::Expired, self::Cancelled, self::Superseded],
            self::Rejected, self::Expired => [self::Superseded],
            self::Accepted, self::Cancelled, self::Superseded => [],
        };

        return in_array($status, $allowed, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Awaiting approval',
            self::Approved => 'Approved',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::Superseded => 'Superseded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Superseded => 'secondary',
            self::PendingApproval, self::Expired => 'warning',
            self::Approved, self::Sent => 'info',
            self::Accepted => 'success',
            self::Rejected, self::Cancelled => 'danger',
        };
    }

    /**
     * Statuses from which a new revision may be prepared.
     */
    public function isRevisable(): bool
    {
        return in_array($this, [self::Approved, self::Sent, self::Rejected, self::Expired], true);
    }

    /**
     * Statuses that can still be cancelled (anything before the customer's decision).
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::Approved, self::Sent], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
