<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('warehouses.manage') || $actor->can('stock.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('warehouses.manage');
    }

    public function update(User $actor, Warehouse $warehouse): bool
    {
        return $actor->can('warehouses.manage');
    }
}
