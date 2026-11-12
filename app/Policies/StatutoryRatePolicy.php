<?php

namespace App\Policies;

use App\Models\StatutoryRate;
use App\Models\User;

/**
 * Statutory rule sets (FR-PRL-03) are editable by authorised users (payroll.configure) until an approved or paid
 * run has used them; payroll staff can read them.
 */
class StatutoryRatePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('payroll.configure') || $actor->can('payroll.prepare') || $actor->can('payroll.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('payroll.configure');
    }

    public function update(User $actor, StatutoryRate $rate): bool
    {
        return $actor->can('payroll.configure') && ! $rate->isLocked();
    }

    public function verify(User $actor, StatutoryRate $rate): bool
    {
        return $actor->can('payroll.configure') && ! $rate->isVerified();
    }
}
