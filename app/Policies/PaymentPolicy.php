<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Payments are viewed, recorded (see InvoicePolicy::recordPayment) and reversed — never edited or deleted.
 */
class PaymentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('payments.view');
    }

    public function view(User $actor, Payment $payment): bool
    {
        return $actor->can('payments.view');
    }

    public function reverse(User $actor, Payment $payment): bool
    {
        return $actor->can('payments.reverse') && $payment->isCompleted();
    }
}
