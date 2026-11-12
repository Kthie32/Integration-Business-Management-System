<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Services\Payroll\SalaryStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Recording an employee's salary structure from a date, and removing the latest one before it is used
 * (FR-PRL-02). Shown on the employee page to users with salary access.
 */
class SalaryStructureController extends Controller
{
    public function __construct(private SalaryStructureService $structures) {}

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manageSalary', $employee);

        $data = $request->validateWithBag('salary', [
            'basic_salary' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'components' => ['nullable', 'array'],
            'components.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->structures->create($employee, $data, $request->user());

        return redirect()->to(route('employees.show', $employee).'#salary')->with('success', 'Salary structure recorded.');
    }

    public function destroy(Employee $employee, SalaryStructure $structure): RedirectResponse
    {
        Gate::authorize('manageSalary', $employee);
        abort_unless($structure->employee_id === $employee->id, 404);

        $this->structures->delete($structure);

        return redirect()->to(route('employees.show', $employee).'#salary')->with('success', 'Salary structure removed.');
    }
}
