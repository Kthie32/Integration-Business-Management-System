<?php

namespace App\Http\Controllers\HR;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\ChangeEmployeeStatusRequest;
use App\Models\Employee;
use App\Services\HR\EmployeeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

/**
 * Leave, suspension, reinstatement and termination of an employee.
 */
class EmployeeStatusController extends Controller
{
    public function __invoke(ChangeEmployeeStatusRequest $request, Employee $employee, EmployeeService $employees): RedirectResponse
    {
        $status = EmployeeStatus::from($request->validated('status'));

        $employees->changeStatus(
            $employee,
            $status,
            CarbonImmutable::parse($request->validated('effective_date')),
            $request->validated('reason'),
            $request->user(),
        );

        $redirect = redirect()->route('employees.show', $employee)->with('success', "Status changed to {$status->label()}.");

        if ($status === EmployeeStatus::Terminated && $employee->user?->is_active) {
            $redirect->with('warning', "{$employee->user->name}'s user account is still active. Ask an administrator to deactivate it if they should no longer sign in.");
        }

        return $redirect;
    }
}
