<?php

namespace App\Enums;

/**
 * The state of one approval step of one document. "Skipped" steps had nobody eligible to act (for example
 * the requester heads the department); "Not reached" steps were still open when the document was rejected,
 * returned or withdrawn.
 */
enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Skipped = 'skipped';
    case NotReached = 'not_reached';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Returned => 'Returned for revision',
            self::Skipped => 'Skipped',
            self::NotReached => 'Not reached',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Returned => 'info',
            self::Skipped, self::NotReached => 'secondary',
        };
    }
}
