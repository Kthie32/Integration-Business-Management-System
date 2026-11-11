<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

/**
 * Drafts are edited, recorded or deleted by anyone who may record expenses; recorded expenses are
 * read-only and can only be voided (FR-EXP-03).
 */
class ExpensePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('expenses.view');
    }

    public function view(User $actor, Expense $expense): bool
    {
        return $actor->can('expenses.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('expenses.create');
    }

    public function update(User $actor, Expense $expense): bool
    {
        return $actor->can('expenses.create') && $expense->isDraft();
    }

    public function delete(User $actor, Expense $expense): bool
    {
        return $actor->can('expenses.create') && $expense->isDraft();
    }

    public function record(User $actor, Expense $expense): bool
    {
        return $actor->can('expenses.create') && $expense->isDraft();
    }

    public function void(User $actor, Expense $expense): bool
    {
        return $actor->can('expenses.void') && $expense->isRecorded();
    }
}
