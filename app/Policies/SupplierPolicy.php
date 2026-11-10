<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('suppliers.view');
    }

    public function view(User $actor, Supplier $supplier): bool
    {
        return $actor->can('suppliers.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('suppliers.create');
    }

    public function update(User $actor, Supplier $supplier): bool
    {
        return $actor->can('suppliers.update');
    }

    public function delete(User $actor, Supplier $supplier): bool
    {
        return $actor->can('suppliers.delete');
    }
}
