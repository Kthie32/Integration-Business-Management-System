<?php

namespace Tests\Concerns;

use App\Enums\ApprovalDecision;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\SalaryStructureService;
use Database\Seeders\PayrollSeeder;

/**
 * Helpers for payroll tests. Requires roles and permissions to be seeded.
 */
trait CreatesPayroll
{
    protected function seedPayroll(): void
    {
        $this->seed(PayrollSeeder::class);
    }

    /**
     * An employee hired in 2024 with a salary structure from their hire date.
     *
     * @param  array<int, string>  $components  pay component id => value
     * @param  array<string, mixed>  $attributes
     */
    protected function employeeWithSalary(string $basic, array $components = [], array $attributes = []): Employee
    {
        $employee = Employee::factory()->create(['hire_date' => '2024-01-01', ...$attributes]);

        app(SalaryStructureService::class)->create($employee, [
            'basic_salary' => $basic,
            'effective_from' => '2024-01-01',
            'components' => $components,
        ], $this->payrollAdmin());

        return $employee->refresh();
    }

    protected function payrollAdmin(): User
    {
        return User::query()->where('email', 'payroll-admin@example.test')->first()
            ?? User::factory()->create(['email' => 'payroll-admin@example.test']);
    }

    protected function draftRun(User $preparer, ?int $year = null, ?int $month = null): PayrollRun
    {
        return app(PayrollService::class)->create([
            'period_year' => $year ?? today()->year,
            'period_month' => $month ?? today()->month,
        ], $preparer);
    }

    protected function calculatedRun(User $preparer, ?int $year = null, ?int $month = null): PayrollRun
    {
        return app(PayrollService::class)->calculate($this->draftRun($preparer, $year, $month), $preparer);
    }

    protected function submittedRun(User $preparer, ?int $year = null, ?int $month = null): PayrollRun
    {
        return app(PayrollService::class)->submit($this->calculatedRun($preparer, $year, $month), $preparer);
    }

    protected function approvedRun(User $preparer, User $approver, ?int $year = null, ?int $month = null): PayrollRun
    {
        return app(PayrollService::class)->decide($this->submittedRun($preparer, $year, $month), $approver, ApprovalDecision::Approve);
    }
}
