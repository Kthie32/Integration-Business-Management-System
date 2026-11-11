<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;

/**
 * Receipts are only viewed and printed. They are created with their payment and voided by reversing it.
 */
class ReceiptPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('payments.view');
    }

    public function view(User $actor, Receipt $receipt): bool
    {
        return $actor->can('payments.view');
    }

    /**
     * Reversing from the receipt reverses its payment (FR-PAY-05).
     */
    public function reverse(User $actor, Receipt $receipt): bool
    {
        return $actor->can('payments.reverse') && $receipt->isValid();
    }
}
