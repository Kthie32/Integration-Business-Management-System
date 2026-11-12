<?php

namespace App\Enums;

/**
 * What an approver can do at their step (FR-REQ-04). Rejecting and returning need a comment.
 */
enum ApprovalDecision: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Return = 'return';

    public function requiresComment(): bool
    {
        return $this !== self::Approve;
    }

    public function resultingStatus(): ApprovalStatus
    {
        return match ($this) {
            self::Approve => ApprovalStatus::Approved,
            self::Reject => ApprovalStatus::Rejected,
            self::Return => ApprovalStatus::Returned,
        };
    }
}
