<?php

namespace Tests\Feature\Payroll;

use App\Enums\ApprovalDecision;
use App\Enums\EmployeeStatus;
use App\Enums\PayComponentKind;
use App\Enums\PayrollRunStatus;
use App\Enums\PayslipPaymentStatus;
use App\Enums\StatutoryRateCode;
use App\Enums\SystemRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollRun;
use App\Models\StatutoryRate;
use App\Models\User;
use App\Services\Payroll\PayrollConfigurationService;
use App\Services\Payroll\PayrollService;
use Database\Seeders\PayrollSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPayroll;
use Tests\TestCase;

class PayrollRunTest extends TestCase
{
    use CreatesPayroll, RefreshDatabase;

    private const YEAR = 2026;

    private const MONTH = 11;

    private User $preparer;

    private User $approver;

    private Employee $manager;

    private Employee $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seedPayroll();

        $this->preparer = $this->userWithRole(SystemRole::HumanResourceOfficer);
        $this->approver = $this->userWithRole(SystemRole::FinanceOfficer);

        $housing = PayComponent::query()->where('code', 'HOUSING')->sole();
        $this->manager = $this->employeeWithSalary('1000000', [$housing->id => '200000'], ['last_name' => 'Akello']);
        $this->clerk = $this->employeeWithSalary('300000', [], ['last_name' => 'Byaruhanga']);
    }

    private function service(): PayrollService
    {
        return app(PayrollService::class);
    }

    private function calculated(?User $preparer = null): PayrollRun
    {
        return $this->calculatedRun($preparer ?? $this->preparer, self::YEAR, self::MONTH);
    }

    public function test_calculation_pays_eligible_employees_and_lists_the_skipped(): void
    {
        $noSalary = Employee::factory()->create(['hire_date' => '2024-01-01', 'last_name' => 'Cheptoo']);
        $this->employeeWithSalary('500000', [], ['status' => EmployeeStatus::Suspended, 'last_name' => 'Doka']);
        $this->employeeWithSalary('500000', [], ['status' => EmployeeStatus::Terminated, 'termination_date' => '2026-10-15', 'last_name' => 'Emong']);
        $this->employeeWithSalary('500000', [], ['status' => EmployeeStatus::Terminated, 'termination_date' => '2026-11-10', 'last_name' => 'Fred']);
        $this->employeeWithSalary('500000', [], ['status' => EmployeeStatus::OnLeave, 'last_name' => 'Gimei']);

        $run = $this->calculated();

        $this->assertSame(PayrollRunStatus::Calculated, $run->status);
        $this->assertSame(4, $run->employee_count);
        $this->assertEqualsCanonicalizing([$this->manager->id, $this->clerk->id, ...Employee::query()->whereIn('last_name', ['Fred', 'Gimei'])->pluck('id')],
            $run->items()->pluck('employee_id')->all());
        $this->assertEqualsCanonicalizing(['No salary structure in effect', 'Suspended'], array_column($run->skipped_employees, 'reason'));
        $this->assertContains($noSalary->id, array_column($run->skipped_employees, 'employee_id'));
        $this->assertSame($this->preparer->id, $run->prepared_by);
    }

    public function test_totals_and_line_breakdown_are_stored_for_each_payslip(): void
    {
        $run = $this->calculated();

        $this->assertSame('1500000.00', (string) $run->total_gross);
        $this->assertSame('268500.00', (string) $run->total_paye);
        $this->assertSame('75000.00', (string) $run->total_nssf_employee);
        $this->assertSame('150000.00', (string) $run->total_nssf_employer);
        $this->assertSame('0.00', (string) $run->total_lst);
        $this->assertSame('343500.00', (string) $run->total_deductions);
        $this->assertSame('1156500.00', (string) $run->total_net);
        $this->assertSame('1650000.00', (string) $run->total_employer_cost);
        $this->assertCount(4, $run->statutory_rate_ids);

        $item = $run->items()->where('employee_id', $this->manager->id)->with('lines')->sole();
        $this->assertSame('878000.00', (string) $item->net_pay);
        $this->assertSame('1200000.00', (string) $item->taxable_pay);
        $this->assertSame(['BASIC', 'HOUSING', 'PAYE', 'NSSF_EE', 'NSSF_ER'], $item->lines->pluck('code')->all());
        $this->assertSame($this->manager->employee_number, $item->employee_number);
        $this->assertNull($item->payslip_number);
    }

    public function test_whoever_created_calculated_or_submitted_a_run_cannot_approve_it(): void
    {
        $superAdmin = $this->userWithRole(SystemRole::SuperAdministrator);
        $otherPreparer = $this->userWithRole(SystemRole::Accountant);

        $run = $this->draftRun($superAdmin, self::YEAR, self::MONTH);
        $run = $this->service()->calculate($run, $this->preparer);
        $run = $this->service()->submit($run, $otherPreparer);

        foreach ([$superAdmin, $this->preparer, $otherPreparer] as $involved) {
            $this->assertFalse($involved->can('decide', $run->refresh()), "User {$involved->email} should not be able to decide.");

            try {
                $this->service()->decide($run, $involved, ApprovalDecision::Approve);
                $this->fail('A person involved in preparing the run approved it.');
            } catch (BusinessRuleException) {
                $this->assertSame(PayrollRunStatus::PendingApproval, $run->refresh()->status);
            }
        }

        $this->assertTrue($this->approver->can('decide', $run));
        $run = $this->service()->decide($run, $this->approver, ApprovalDecision::Approve);
        $this->assertSame(PayrollRunStatus::Approved, $run->status);
        $this->assertSame($this->approver->id, $run->approved_by);
    }

    public function test_approval_numbers_the_payslips_and_locks_the_run(): void
    {
        $run = $this->approvedRun($this->preparer, $this->approver, self::YEAR, self::MONTH);

        $this->assertMatchesRegularExpression('/^PAY-\d{4}-\d{5}$/', $run->number);
        $numbers = $run->items()->orderBy('employee_name')->pluck('payslip_number')->all();
        $this->assertCount(2, array_filter($numbers));
        $this->assertMatchesRegularExpression('/^PSL-\d{4}-\d{5}$/', $numbers[0]);

        foreach ([
            fn () => $this->service()->calculate($run, $this->preparer),
            fn () => $this->service()->addAdjustment($run, ['employee_id' => $this->clerk->id, 'kind' => 'allowance', 'description' => 'Bonus', 'amount' => '1000'], $this->preparer),
            fn () => $this->service()->delete($run),
        ] as $action) {
            try {
                $action();
                $this->fail('An approved run was changed.');
            } catch (BusinessRuleException) {
            }
        }

        $this->assertFalse($this->preparer->can('prepare', $run));
        $this->assertSame('1156500.00', (string) $run->refresh()->total_net);
    }

    public function test_a_returned_run_is_corrected_and_resubmitted_into_a_new_round_with_the_same_number(): void
    {
        $run = $this->submittedRun($this->preparer, self::YEAR, self::MONTH);
        $number = $run->number;

        try {
            $this->service()->decide($run, $this->approver, ApprovalDecision::Return);
            $this->fail('Returning without a comment was accepted.');
        } catch (BusinessRuleException) {
        }

        $run = $this->service()->decide($run, $this->approver, ApprovalDecision::Return, 'Add the clerk\'s overtime');
        $this->assertSame(PayrollRunStatus::Calculated, $run->status);

        $this->service()->addAdjustment($run, ['employee_id' => $this->clerk->id, 'kind' => 'allowance', 'description' => 'Overtime', 'amount' => '100000', 'is_taxable' => true, 'is_nssf_applicable' => true], $this->preparer);
        $run = $this->service()->submit($run->refresh(), $this->preparer);

        $this->assertSame($number, $run->number);
        $this->assertSame(2, $run->currentApprovalRound());
        $this->assertSame('1600000.00', (string) $run->total_gross);
    }

    public function test_adjustments_recalculate_a_calculated_run(): void
    {
        $run = $this->calculated();

        $adjustment = $this->service()->addAdjustment($run, [
            'employee_id' => $this->clerk->id, 'kind' => PayComponentKind::Allowance->value, 'description' => 'Arrears for October',
            'amount' => '100000', 'is_taxable' => true, 'is_nssf_applicable' => true,
        ], $this->preparer);

        $item = $run->items()->where('employee_id', $this->clerk->id)->with('lines')->sole();
        $this->assertSame('400000.00', (string) $item->gross_pay);
        $this->assertSame('23000.00', (string) $item->paye);
        $this->assertSame('20000.00', (string) $item->nssf_employee);
        $this->assertContains('Arrears for October', $item->lines->pluck('description')->all());

        $this->service()->addAdjustment($run, ['employee_id' => $this->clerk->id, 'kind' => 'deduction', 'description' => 'Advance recovery', 'amount' => '50000'], $this->preparer);
        $this->assertSame('50000.00', (string) $run->items()->where('employee_id', $this->clerk->id)->sole()->other_deductions);

        $this->service()->removeAdjustment($run, $adjustment, $this->preparer);
        $this->assertSame('300000.00', (string) $run->items()->where('employee_id', $this->clerk->id)->sole()->gross_pay);
    }

    public function test_finance_marks_an_approved_run_paid_and_employees_are_told_their_payslip_is_ready(): void
    {
        $account = $this->userWithRole(SystemRole::GeneralEmployee);
        $account->forceFill(['employee_id' => $this->clerk->id])->save();
        $run = $this->approvedRun($this->preparer, $this->approver, self::YEAR, self::MONTH);

        foreach ([today()->addDay()->toDateString(), today()->subYears(2)->toDateString()] as $wrongDate) {
            try {
                $this->service()->markPaid($run, $this->approver, $wrongDate, null);
                $this->fail('An invalid payment date was accepted.');
            } catch (BusinessRuleException) {
            }
        }

        $run = $this->service()->markPaid($run, $this->approver, today()->toDateString(), 'BATCH-778');

        $this->assertSame(PayrollRunStatus::Paid, $run->status);
        $this->assertSame('BATCH-778', $run->payment_reference);
        $this->assertSame([PayslipPaymentStatus::Paid], $run->items()->get()->pluck('payment_status')->unique()->values()->all());
        $this->assertStringContainsString('Your payslip for November 2026', $account->notifications()->sole()->data['title']);
    }

    public function test_one_regular_run_per_month_and_only_never_submitted_runs_are_deleted(): void
    {
        $run = $this->draftRun($this->preparer, self::YEAR, self::MONTH);

        $this->expectExceptionObject(new BusinessRuleException('A payroll run for November 2026 already exists (Draft #'.$run->id.').'));

        try {
            $this->draftRun($this->preparer, self::YEAR, self::MONTH);
        } finally {
            $this->service()->delete($run);
            $this->assertModelMissing($run);
        }
    }

    public function test_runs_cannot_be_prepared_more_than_a_month_ahead(): void
    {
        $this->expectException(BusinessRuleException::class);

        $this->draftRun($this->preparer, today()->addMonths(2)->year, today()->addMonths(2)->month);
    }

    public function test_calculation_stops_when_rules_are_missing_or_deductions_exceed_pay(): void
    {
        $run = $this->draftRun($this->preparer, self::YEAR, self::MONTH);
        $advance = PayComponent::query()->where('code', 'ADVANCE')->sole();
        $this->employeeWithSalary('100000', [$advance->id => '500000'], ['first_name' => 'Overdrawn', 'last_name' => 'Zed']);

        try {
            $this->service()->calculate($run, $this->preparer);
            $this->fail('A negative net pay was accepted.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('Overdrawn', $exception->getMessage());
            $this->assertSame(0, $run->items()->count());
            $this->assertSame(PayrollRunStatus::Draft, $run->refresh()->status);
        }

        StatutoryRate::query()->where('code', StatutoryRateCode::Nssf->value)->delete();
        $this->expectExceptionMessage('No NSSF contributions rules are in effect');
        $this->service()->calculate($run, $this->preparer);
    }

    public function test_the_rules_in_effect_at_month_end_are_used_and_locked_once_approved(): void
    {
        $configuration = app(PayrollConfigurationService::class);
        $newNssf = $configuration->createRate(StatutoryRateCode::Nssf, ['effective_from' => '2026-11-01', 'employee_rate' => '6', 'employer_rate' => '12'], $this->preparer);

        $this->assertSame('2026-10-31', StatutoryRate::query()->where('code', 'NSSF')->where('effective_from', PayrollSeeder::EFFECTIVE_FROM)->sole()->effective_to->toDateString());

        $run = $this->approvedRun($this->preparer, $this->approver, self::YEAR, self::MONTH);

        $this->assertSame('90000.00', (string) $run->total_nssf_employee);
        $this->assertContains($newNssf->id, $run->statutory_rate_ids);
        $this->assertTrue($newNssf->isLocked());

        $this->expectException(BusinessRuleException::class);
        $configuration->updateRate($newNssf, ['employee_rate' => '5', 'employer_rate' => '10']);
    }

    public function test_web_flow_from_creation_to_payment(): void
    {
        $this->actingAs($this->preparer)
            ->post(route('payroll-runs.store'), ['period_year' => self::YEAR, 'period_month' => self::MONTH])
            ->assertRedirect();
        $run = PayrollRun::query()->sole();

        $this->actingAs($this->preparer)->post(route('payroll-runs.calculate', $run))->assertSessionHas('success');
        $this->actingAs($this->preparer)->get(route('payroll-runs.show', $run))->assertOk()->assertSee('Byaruhanga')->assertSee('Recalculate');
        $this->actingAs($this->preparer)->post(route('payroll-runs.submit', $run))->assertSessionHas('success');

        $this->actingAs($this->preparer)->post(route('payroll-runs.approve', $run))->assertForbidden();
        $this->actingAs($this->approver)->get(route('payroll-runs.show', $run))->assertOk()->assertSee('Your decision');
        $this->actingAs($this->approver)->post(route('payroll-runs.return', $run), ['comment' => ''])->assertSessionHasErrors('comment');
        $this->actingAs($this->approver)->post(route('payroll-runs.approve', $run))->assertSessionHas('success');
        $this->assertSame(PayrollRunStatus::Approved, $run->refresh()->status);

        $this->actingAs($this->preparer)->post(route('payroll-runs.mark-paid', $run), ['paid_at' => today()->toDateString()])->assertForbidden();
        $this->actingAs($this->approver)->post(route('payroll-runs.mark-paid', $run), ['paid_at' => today()->toDateString(), 'payment_reference' => 'EFT 12'])
            ->assertSessionHas('success');
        $this->assertSame(PayrollRunStatus::Paid, $run->refresh()->status);

        $this->actingAs($this->approver)->get(route('payroll-runs.index'))->assertOk()->assertSee('November 2026');
    }
}
