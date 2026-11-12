<?php

namespace Tests\Feature\Payroll;

use App\Enums\ApprovalDecision;
use App\Enums\SystemRole;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\StatutoryRate;
use App\Models\User;
use App\Services\Approval\ApprovalInbox;
use App\Services\Payroll\PayrollReportService;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesPayroll;
use Tests\TestCase;

class PayslipAndReportTest extends TestCase
{
    use CreatesPayroll, RefreshDatabase;

    private User $preparer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seedPayroll();
        $this->preparer = $this->userWithRole(SystemRole::HumanResourceOfficer);
        $this->approver = $this->userWithRole(SystemRole::Manager);
    }

    public function test_employees_see_only_their_own_payslips_and_only_once_approved(): void
    {
        $mine = $this->employeeWithSalary('750000', [], ['last_name' => 'Mine']);
        $this->employeeWithSalary('650000', [], ['last_name' => 'Theirs']);
        $user = $this->userWithRole(SystemRole::GeneralEmployee);
        $user->forceFill(['employee_id' => $mine->id])->save();

        $run = $this->submittedRun($this->preparer, 2026, 10);
        $own = PayrollItem::query()->where('employee_id', $mine->id)->sole();
        $other = PayrollItem::query()->where('employee_id', '!=', $mine->id)->sole();

        $this->actingAs($user)->get(route('payslips.show', $own))->assertForbidden();
        $this->actingAs($user)->get(route('payslips.mine'))->assertOk()->assertSee('No payslips yet.');

        app(PayrollService::class)->decide($run, $this->approver, ApprovalDecision::Approve);

        $this->actingAs($user)->get(route('payslips.mine'))->assertOk()->assertSee('October 2026')->assertSee($own->refresh()->payslip_number);
        $this->actingAs($user)->get(route('payslips.show', $own))->assertOk()->assertSee('Net pay')->assertDontSee('Theirs');
        $this->actingAs($user)->get(route('payslips.show', $other))->assertForbidden();
        $this->actingAs($user)->get(route('payroll-runs.show', $run))->assertForbidden();

        $pdf = $this->actingAs($user)->get(route('payslips.pdf', $own));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->actingAs($user)->get(route('payslips.print', $own))->assertOk()->assertSee('PAYSLIP')->assertDontSee('NOT APPROVED');
    }

    public function test_unlinked_accounts_are_told_to_ask_hr(): void
    {
        $this->actingAs($this->userWithRole(SystemRole::GeneralEmployee))->get(route('payslips.mine'))
            ->assertOk()->assertSee('not linked to an employee record');
    }

    public function test_payroll_staff_see_unapproved_payslips_with_a_watermark(): void
    {
        $this->employeeWithSalary('500000');
        $run = $this->calculatedRun($this->preparer, 2026, 10);
        $item = $run->items()->sole();

        $this->actingAs($this->preparer)->get(route('payslips.show', $item))->assertOk()->assertSee('not approved yet');
        $this->actingAs($this->preparer)->get(route('payslips.print', $item))->assertOk()->assertSee('NOT APPROVED');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reports(): array
    {
        return collect(array_keys(PayrollReportService::REPORTS))->mapWithKeys(fn (string $report): array => [$report => [$report]])->all();
    }

    #[DataProvider('reports')]
    public function test_reports_export_as_pdf_excel_and_print(string $report): void
    {
        $this->employeeWithSalary('1000000', [], ['bank_name' => 'Stanbic Bank', 'bank_account' => '9030011122', 'tin' => '1000123456']);
        $run = $this->approvedRun($this->preparer, $this->approver, 2026, 10);

        $pdf = $this->actingAs($this->approver)->get(route('payroll-runs.export', [$run, $report, 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->actingAs($this->approver)->get(route('payroll-runs.export', [$run, $report, 'xlsx']));
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', (string) file_get_contents($xlsx->getFile()->getPathname()));

        $print = $this->actingAs($this->approver)->get(route('payroll-runs.export', [$run, $report, 'print']));
        $print->assertOk()->assertSee(PayrollReportService::REPORTS[$report]);

        $this->actingAs($this->userWithRole(SystemRole::GeneralEmployee))->get(route('payroll-runs.export', [$run, $report, 'pdf']))->assertForbidden();
    }

    public function test_report_rows_and_totals(): void
    {
        $this->employeeWithSalary('1000000', [], ['bank_name' => 'Stanbic Bank', 'bank_account' => '9030011122', 'last_name' => 'Apio']);
        $this->employeeWithSalary('300000', [], ['mobile_money_number' => '+256772000111', 'last_name' => 'Bossa']);
        $run = $this->approvedRun($this->preparer, $this->approver, 2026, 10);
        $reports = app(PayrollReportService::class);

        $register = $reports->build($run, 'register');
        $this->assertCount(2, $register['rows']);
        $this->assertSame('1300000.00', $register['totals'][5]);
        $this->assertSame((string) $run->total_net, $register['totals'][10]);

        $deductions = $reports->build($run, 'deductions');
        $this->assertSame('208500.00', $deductions['totals'][6]);
        $this->assertSame('195000.00', $deductions['totals'][10]);

        $payments = $reports->build($run, 'payments');
        $this->assertSame(['Bank transfer', 'Mobile money'], array_column($payments['rows'], 2));
        $this->assertSame(['9030011122', '+256772000111'], array_column($payments['rows'], 4));
    }

    public function test_a_draft_run_has_no_reports(): void
    {
        $run = $this->draftRun($this->preparer, 2026, 10);

        $this->actingAs($this->approver)->get(route('payroll-runs.export', [$run, 'register', 'pdf']))->assertForbidden();
    }

    public function test_the_inbox_and_dashboard_show_payroll_to_approvers_but_not_to_the_preparer(): void
    {
        $this->employeeWithSalary('500000');
        $run = $this->submittedRun($this->preparer, 2026, 10);
        $inbox = app(ApprovalInbox::class);

        $this->assertSame(['Payroll run'], $inbox->itemsFor($this->approver)->pluck('type')->all());
        $this->assertTrue($inbox->itemsFor($this->preparer)->isEmpty());
        $this->actingAs($this->approver)->get(route('approvals.inbox'))->assertOk()->assertSee($run->number);

        $this->actingAs($this->approver)->get(route('dashboard'))->assertOk()->assertSee('Last payroll (net pay)')->assertSee('None yet');
        app(PayrollService::class)->decide($run, $this->approver, ApprovalDecision::Approve);
        $this->actingAs($this->approver)->get(route('dashboard'))->assertOk()->assertSee('October 2026');
        $this->actingAs($this->userWithRole(SystemRole::GeneralEmployee))->get(route('dashboard'))->assertOk()->assertDontSee('Last payroll');
    }

    public function test_a_payroll_workflow_can_route_runs_to_a_named_approver(): void
    {
        $this->employeeWithSalary('500000');
        $named = $this->userWithRole(SystemRole::FinanceOfficer);
        $this->actingAs($this->userWithRole(SystemRole::SuperAdministrator))->post(route('approval-workflows.store'), [
            'document_type' => 'payroll_run', 'name' => 'Payroll sign-off', 'is_active' => '1',
            'steps' => [['approver_type' => 'user', 'user_id' => $named->id]],
        ])->assertSessionHasNoErrors();

        $run = $this->submittedRun($this->preparer, 2026, 10);

        $this->assertFalse($this->approver->can('decide', $run));
        $this->assertTrue($named->can('decide', $run));
    }

    /**
     * @return array<string, array{SystemRole, array{viewRuns: bool, prepare: bool, approve: bool, markPaid: bool, configure: bool, ownPayslips: bool}}>
     */
    public static function roleMatrix(): array
    {
        $none = ['viewRuns' => false, 'prepare' => false, 'approve' => false, 'markPaid' => false, 'configure' => false, 'ownPayslips' => true];

        return [
            'super administrator' => [SystemRole::SuperAdministrator, ['viewRuns' => true, 'prepare' => true, 'approve' => true, 'markPaid' => true, 'configure' => true, 'ownPayslips' => true]],
            'system administrator' => [SystemRole::SystemAdministrator, $none],
            'manager' => [SystemRole::Manager, [...$none, 'viewRuns' => true, 'approve' => true]],
            'finance officer' => [SystemRole::FinanceOfficer, [...$none, 'viewRuns' => true, 'approve' => true, 'markPaid' => true]],
            'accountant' => [SystemRole::Accountant, [...$none, 'viewRuns' => true, 'prepare' => true]],
            'human resource officer' => [SystemRole::HumanResourceOfficer, [...$none, 'viewRuns' => true, 'prepare' => true, 'configure' => true]],
            'procurement officer' => [SystemRole::ProcurementOfficer, $none],
            'inventory officer' => [SystemRole::InventoryOfficer, $none],
            'department head' => [SystemRole::DepartmentHead, $none],
            'general employee' => [SystemRole::GeneralEmployee, $none],
        ];
    }

    /**
     * @param  array{viewRuns: bool, prepare: bool, approve: bool, markPaid: bool, configure: bool, ownPayslips: bool}  $expected
     */
    #[DataProvider('roleMatrix')]
    public function test_role_matrix(SystemRole $role, array $expected): void
    {
        $user = $this->userWithRole($role);

        $this->assertSame($expected, [
            'viewRuns' => $user->can('viewAny', PayrollRun::class),
            'prepare' => $user->can('create', PayrollRun::class),
            'approve' => $user->can('payroll.approve'),
            'markPaid' => $user->can('payroll.mark_paid'),
            'configure' => $user->can('create', StatutoryRate::class),
            'ownPayslips' => $user->can('payslips.view_own'),
        ]);

        $this->actingAs($user)->get(route('payroll-runs.index'))->assertStatus($expected['viewRuns'] ? 200 : 403);
    }
}
