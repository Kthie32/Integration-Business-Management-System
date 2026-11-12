<?php

namespace App\Enums;

/**
 * The effect of an approval decision on the whole document.
 */
enum ApprovalOutcome: string
{
    /** The step was approved and a later step now waits. */
    case Advanced = 'advanced';

    /** Every step is resolved; the document is approved. */
    case Approved = 'approved';

    case Rejected = 'rejected';

    case Returned = 'returned';
}
