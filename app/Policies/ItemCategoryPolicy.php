<?php

namespace App\Policies;

use App\Models\ItemCategory;
use App\Models\User;

class ItemCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('items.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('items.manage');
    }

    public function update(User $actor, ItemCategory $category): bool
    {
        return $actor->can('items.manage');
    }

    public function delete(User $actor, ItemCategory $category): bool
    {
        return $actor->can('items.manage');
    }
}
