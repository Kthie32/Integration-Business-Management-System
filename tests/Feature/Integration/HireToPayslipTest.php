<?php

namespace Tests\Feature\Integration;

use App\Enums\PayrollRunStatus;
use App\Enums\SystemRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\PayrollSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * From hiring to the payslip: HR records the employee and salary, prepares and submits the run, Finance approves and
 * pays it, the employee opens their own payslip, and the run appears in the payroll and finance reports.
 */
class HireToPayslipTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 2026;

    private const MONTH = 10;

    private User $hr;

    private User $financeOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(PayrollSeeder::class);
        $this->hr = $this->userWithRole(SystemRole::HumanResourceOfficer);
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
    }

    public function test_an_employee_is_hired_paid_through_an_approved_run_and_sees_their_payslip(): void
    {
        $account = $this->userWithRole(SystemRole::GeneralEmployee);
        $employee = $this->hrHiresAnEmployee($account);
        $this->actingAs($this->hr)->post(route('employees.salary-structures.store', $employee), [
            'basic_salary' => '2400000', 'effective_from' => '2026-01-01',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->hr)->post(route('payroll-runs.store'), ['period_year' => self::YEAR, 'period_month' => self::MONTH])->assertSessionHasNoErrors();
        $run = PayrollRun::query()->sole();
        $this->actingAs($this->hr)->post(route('payroll-runs.calculate', $run))->assertSessionHas('success');
        $this->actingAs($this->hr)->post(route('payroll-runs.submit', $run))->assertSessionHas('success');
        $this->actingAs($this->hr)->post(route('payroll-runs.approve', $run))->assertForbidden();
        $this->actingAs($this->financeOfficer)->post(route('payroll-runs.approve', $run))->assertSessionHas('success');
        $this->actingAs($this->financeOfficer)->post(route('payroll-runs.mark-paid', $run), [
            'paid_at' => today()->toDateString(), 'payment_reference' => 'BATCH-OCT',
        ])->assertSessionHas('success');

        $run->refresh();
        $payslip = $run->items()->sole();
        $this->assertSame(PayrollRunStatus::Paid, $run->status);
        $this->assertSame('2400000.00', (string) $payslip->gross_pay);
        $this->assertTrue(bccomp((string) $payslip->net_pay, '2400000', 2) < 0);
        $this->assertNotNull($payslip->payslip_number);

        $mine = $this->actingAs($account)->get(route('payslips.mine'));
        $mine->assertOk();
        $mine->assertSee($payslip->payslip_number);
        $this->actingAs($account)->get(route('payslips.pdf', $payslip))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->hr)->post(route('employees.salary-structures.store', $employee), [
            'basic_salary' => '3000000', 'effective_from' => '2026-10-01',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2400000.00', (string) $payslip->refresh()->gross_pay);

        $period = ['from' => '2026-10-01', 'to' => '2026-10-31'];
        $summary = $this->actingAs($this->userWithRole(SystemRole::Manager))->get(route('reports.show', ['report' => 'payroll-summary', ...$period]));
        $summary->assertOk();
        $summary->assertSee($run->number);
        $this->actingAs($this->hr)->get(route('payroll-runs.export', [$run, 'register', 'xlsx']))->assertOk();
        $expenses = $this->actingAs($this->financeOfficer)->get(route('reports.show', ['report' => 'expenses', ...$period]));
        $expenses->assertOk();
        $expenses->assertSee('Payroll');
    }

    private function hrHiresAnEmployee(User $account): Employee
    {
        $department = Department::factory()->create(['name' => 'Field Operations']);

        $this->actingAs($this->hr)->post(route('employees.store'), [
            'first_name' => 'Grace', 'last_name' => 'Namuli',
            'department_id' => $department->id, 'job_title' => 'Field officer', 'employment_type' => 'permanent',
            'hire_date' => '2026-01-01', 'is_resident_for_tax' => '1', 'user_id' => $account->id,
            'tin' => '1002003004', 'bank_name' => 'Stanbic Bank', 'bank_account' => '9030012345678',
        ])->assertSessionHasNoErrors();

        $employee = Employee::query()->where('last_name', 'Namuli')->sole();
        $this->assertSame($employee->id, $account->refresh()->employee_id);

        return $employee;
    }
}
