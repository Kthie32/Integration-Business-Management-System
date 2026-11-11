<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

/**
 * Expense categories are finance configuration, managed with the settings.finance permission (docs/07 B21).
 */
class ExpenseCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('expenses.view') || $actor->can('settings.finance');
    }

    public function create(User $actor): bool
    {
        return $actor->can('settings.finance');
    }

    public function update(User $actor, ExpenseCategory $category): bool
    {
        return $actor->can('settings.finance');
    }

    public function delete(User $actor, ExpenseCategory $category): bool
    {
        return $actor->can('settings.finance');
    }
}
