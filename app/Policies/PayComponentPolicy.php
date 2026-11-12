<?php

namespace App\Policies;

use App\Models\PayComponent;
use App\Models\User;

/**
 * Pay components are configured with payroll.configure and visible to payroll preparers.
 */
class PayComponentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('payroll.configure') || $actor->can('payroll.prepare') || $actor->can('payroll.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('payroll.configure');
    }

    public function update(User $actor, PayComponent $component): bool
    {
        return $actor->can('payroll.configure');
    }

    public function delete(User $actor, PayComponent $component): bool
    {
        return $actor->can('payroll.configure');
    }
}
