<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('departments.manage') || $actor->can('employees.view') || $actor->can('employees.view_department');
    }

    public function create(User $actor): bool
    {
        return $actor->can('departments.manage');
    }

    public function update(User $actor, Department $department): bool
    {
        return $actor->can('departments.manage');
    }

    public function delete(User $actor, Department $department): bool
    {
        return $actor->can('departments.manage');
    }
}
