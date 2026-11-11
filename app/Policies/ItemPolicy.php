<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('items.view');
    }

    public function view(User $actor, Item $item): bool
    {
        return $actor->can('items.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('items.manage');
    }

    public function update(User $actor, Item $item): bool
    {
        return $actor->can('items.manage');
    }

    public function delete(User $actor, Item $item): bool
    {
        return $actor->can('items.manage');
    }

    /**
     * The stock card shows quantities and costs, so it needs stock.view as well.
     */
    public function viewStock(User $actor, Item $item): bool
    {
        return $actor->can('items.view') && $actor->can('stock.view');
    }
}
