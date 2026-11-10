<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;

/**
 * Users with employees.view see everyone; Department Heads (employees.view_department) see only employees of the
 * departments their linked employee record heads. Sensitive details and documents need their own permissions.
 */
class EmployeePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('employees.view') || $actor->can('employees.view_department');
    }

    public function view(User $actor, Employee $employee): bool
    {
        if ($actor->can('employees.view')) {
            return true;
        }

        return $actor->can('employees.view_department')
            && $actor->employee_id !== null
            && $employee->department_id !== null
            && Department::query()->whereKey($employee->department_id)->where('head_employee_id', $actor->employee_id)->exists();
    }

    public function create(User $actor): bool
    {
        return $actor->can('employees.create');
    }

    public function update(User $actor, Employee $employee): bool
    {
        return $actor->can('employees.update');
    }

    /**
     * Status changes (leave, suspension, termination). Terminated employees cannot change status again.
     */
    public function changeStatus(User $actor, Employee $employee): bool
    {
        return $actor->can('employees.update') && $this->hasNextStatus($employee);
    }

    /**
     * Identity numbers (NIN, TIN, NSSF) and bank / mobile money details.
     */
    public function viewSensitive(User $actor, Employee $employee): bool
    {
        return $actor->can('employees.view_salary') && $this->view($actor, $employee);
    }

    public function viewDocuments(User $actor, Employee $employee): bool
    {
        return $actor->can('employees.view_documents') && $this->view($actor, $employee);
    }

    public function manageDocuments(User $actor, Employee $employee): bool
    {
        return $actor->can('employees.manage_documents') && $this->view($actor, $employee);
    }

    private function hasNextStatus(Employee $employee): bool
    {
        foreach ($employee->status::cases() as $status) {
            if ($employee->status->canTransitionTo($status)) {
                return true;
            }
        }

        return false;
    }
}
