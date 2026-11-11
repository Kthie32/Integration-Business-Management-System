<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('customers.view');
    }

    public function view(User $actor, Customer $customer): bool
    {
        return $actor->can('customers.view');
    }

    /**
     * A statement shows invoices and payments, so it needs all three view permissions.
     */
    public function viewStatement(User $actor, Customer $customer): bool
    {
        return $actor->can('customers.view') && $actor->can('invoices.view') && $actor->can('payments.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('customers.create');
    }

    public function update(User $actor, Customer $customer): bool
    {
        return $actor->can('customers.update');
    }

    public function delete(User $actor, Customer $customer): bool
    {
        return $actor->can('customers.delete');
    }
}
