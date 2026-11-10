<?php

namespace App\Services\HR;

use App\Enums\DocumentType;
use App\Enums\EmployeeStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\User;
use App\Services\System\NumberingService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Registers employees, records employment-history changes and manages status and account links.
 */
class EmployeeService
{
    public function __construct(private NumberingService $numbering) {}

    /**
     * @param  array<string, mixed>  $data  Validated employee attributes, plus an optional "user_id" to link.
     */
    public function create(array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($data, $actor): Employee {
            $employee = new Employee($data);
            $employee->forceFill([
                'employee_number' => $this->numbering->next(DocumentType::Employee),
                'status' => EmployeeStatus::Active,
                'created_by' => $actor->id,
            ])->save();

            $employee->recordStatusChange(null, EmployeeStatus::Active, 'Employee registered');

            if (array_key_exists('user_id', $data)) {
                $this->linkUser($employee, $data['user_id']);
            }

            return $employee;
        });
    }

    /**
     * Update an employee, recording dated history for changes to department, job title and employment type.
     *
     * @param  array<string, mixed>  $data  Validated attributes, plus optional "user_id", "effective_date" and "change_notes".
     */
    public function update(Employee $employee, array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $data, $actor): Employee {
            $effectiveDate = CarbonImmutable::parse($data['effective_date'] ?? now());
            $before = $employee->only(array_keys(EmploymentHistory::TRACKED_FIELDS));

            $employee->update($data);

            foreach (['department_id', 'job_title', 'employment_type'] as $field) {
                $old = $this->rawValue($before[$field]);
                $new = $this->rawValue($employee->getAttribute($field));

                if ($old !== $new) {
                    $this->recordHistory($employee, 'change', $field, $before[$field], $employee->getAttribute($field), $effectiveDate, $data['change_notes'] ?? null, $actor);
                }
            }

            if (array_key_exists('user_id', $data)) {
                $this->linkUser($employee, $data['user_id']);
            }

            return $employee;
        });
    }

    /**
     * Move an employee to a new employment status. Termination records the date and reason and removes the
     * employee as head of any department.
     */
    public function changeStatus(Employee $employee, EmployeeStatus $status, CarbonImmutable $effectiveDate, ?string $reason, User $actor): void
    {
        if ($status === EmployeeStatus::Terminated && blank($reason)) {
            throw new BusinessRuleException('Give a reason for the termination.');
        }

        DB::transaction(function () use ($employee, $status, $effectiveDate, $reason, $actor): void {
            $previous = $employee->status;

            if ($status === EmployeeStatus::Terminated) {
                $employee->forceFill([
                    'termination_date' => $effectiveDate,
                    'termination_reason' => $reason,
                ]);

                $employee->headedDepartments()->update(['head_employee_id' => null]);
            }

            $employee->transitionTo($status, $reason);

            $this->recordHistory($employee, 'status', 'status', $previous, $status, $effectiveDate, $reason, $actor);
        });
    }

    /**
     * Link a user account to the employee (or unlink with null). An account can belong to one employee only.
     */
    public function linkUser(Employee $employee, int|string|null $userId): void
    {
        $userId = filled($userId) ? (int) $userId : null;
        $current = $employee->user()->first();

        if ($current?->id === $userId) {
            return;
        }

        if ($userId !== null) {
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            if ($user->employee_id !== null && $user->employee_id !== $employee->id) {
                throw new BusinessRuleException("{$user->name}'s account is already linked to another employee.");
            }
        }

        $current?->forceFill(['employee_id' => null])->save();

        if (isset($user)) {
            $user->forceFill(['employee_id' => $employee->id])->save();
        }
    }

    private function recordHistory(
        Employee $employee,
        string $changeType,
        string $field,
        mixed $oldValue,
        mixed $newValue,
        CarbonImmutable $effectiveDate,
        ?string $notes,
        User $actor,
    ): void {
        $employee->employmentHistories()->create([
            'change_type' => $changeType,
            'field' => $field,
            'old_value' => $this->displayValue($field, $oldValue),
            'new_value' => $this->displayValue($field, $newValue),
            'effective_date' => $effectiveDate,
            'notes' => $notes,
            'recorded_by' => $actor->id,
        ]);
    }

    private function rawValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * A readable value for the history: department names and enum labels instead of IDs and codes.
     */
    private function displayValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field === 'department_id') {
            return Department::withTrashed()->whereKey($value)->value('name');
        }

        if ($value instanceof BackedEnum && method_exists($value, 'label')) {
            return $value->label();
        }

        return (string) $value;
    }
}
