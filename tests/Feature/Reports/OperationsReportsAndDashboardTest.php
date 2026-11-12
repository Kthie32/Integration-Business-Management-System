<?php

namespace Tests\Feature\Reports;

use App\Enums\ApprovalDecision;
use App\Enums\StockMovementType;
use App\Enums\SystemRole;
use App\Models\Department;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\Inventory\StockService;
use App\Services\Reports\Definitions\ExpensesReport;
use App\Services\Reports\Definitions\LowStockReport;
use App\Services\Reports\Definitions\PayrollSummaryReport;
use App\Services\Reports\Definitions\RequisitionsReport;
use App\Services\Reports\Definitions\SalarySummaryReport;
use App\Services\Reports\Definitions\StatutoryDeductionsReport;
use App\Services\Reports\Definitions\StockBalancesReport;
use App\Services\Reports\Definitions\StockIssuedReport;
use App\Services\Reports\Definitions\StockReceivedReport;
use App\Services\Reports\ReportFilters;
use Carbon\CarbonImmutable;
use Database\Seeders\ApprovalWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPayroll;
use Tests\Concerns\CreatesRequisitions;
use Tests\TestCase;

/**
 * Inventory, payroll and requisition reports (FR-RPT-03…05) and the permission-aware dashboard (FR-DSH-01…05).
 */
class OperationsReportsAndDashboardTest extends TestCase
{
    use CreatesPayroll, CreatesRequisitions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    private function filters(int $fromDaysAgo = 30, array $options = [], ?int $departmentId = null): ReportFilters
    {
        return new ReportFilters(CarbonImmutable::today()->subDays($fromDaysAgo), CarbonImmutable::today(), $departmentId, $options);
    }

    public function test_stock_reports_value_balances_movements_and_low_stock(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);
        $warehouse = Warehouse::factory()->create(['code' => 'MAIN']);
        $paper = Item::factory()->withMinimum('5')->create(['name' => 'Printer paper']);
        $toner = Item::factory()->withMinimum('2')->create(['name' => 'Toner']);
        $stock = app(StockService::class);

        $stock->receive($paper, $warehouse, '10', '20000');
        $stock->receive($paper, $warehouse, '10', '30000');
        $stock->issue($paper, $warehouse, '12');
        $stock->receive($toner, $warehouse, '1', '150000');
        $reversed = $stock->receive($toner, $warehouse, '4', '150000');
        $stock->reverse($reversed);

        $balances = app(StockBalancesReport::class)->build($this->filters(), $manager);
        $this->assertSame(['Printer paper' => '200000.00', 'Toner' => '150000.00'], array_column($balances->rows, 'value', 'item'));
        $this->assertSame('350000.00', $balances->totals['value']);

        $received = app(StockReceivedReport::class)->build($this->filters(), $manager);
        $this->assertCount(3, $received->rows);
        $this->assertSame('650000.00', $received->totals['value']);

        $issued = app(StockIssuedReport::class)->build($this->filters(), $manager);
        $this->assertSame(['12.000'], array_column($issued->rows, 'quantity'));
        $this->assertSame('300000.00', $issued->totals['value']);

        $low = app(LowStockReport::class)->build($this->filters(), $manager);
        $this->assertSame(['Toner'], array_column($low->rows, 'item'));
        $this->assertSame('1.000', $low->rows[0]['shortfall']);
    }

    public function test_payroll_reports_and_payroll_as_an_expense_category(): void
    {
        $this->seedPayroll();
        $preparer = $this->userWithRole(SystemRole::HumanResourceOfficer);
        $approver = $this->userWithRole(SystemRole::FinanceOfficer);
        $sales = Department::factory()->create(['name' => 'Sales']);
        $this->employeeWithSalary('1000000', [], ['department_id' => $sales->id]);
        $this->employeeWithSalary('600000');

        $month = CarbonImmutable::today()->subMonthNoOverflow();
        $run = $this->approvedRun($preparer, $approver, $month->year, $month->month);
        $this->calculatedRun($preparer, CarbonImmutable::today()->year, CarbonImmutable::today()->month);

        $filters = new ReportFilters($month->startOfMonth(), CarbonImmutable::today());

        $summary = app(PayrollSummaryReport::class)->build($filters, $approver);
        $this->assertCount(1, $summary->rows, 'Only approved runs are reported.');
        $this->assertSame((string) $run->total_net, $summary->totals['net']);
        $this->assertSame('Approved, not yet paid', $summary->rows[0]['status']);

        $statutory = app(StatutoryDeductionsReport::class)->build($filters, $approver);
        $this->assertSame((string) $run->total_paye, $statutory->totals['paye']);
        $this->assertSame(bcadd((string) $run->total_nssf_employee, (string) $run->total_nssf_employer, 2), $statutory->totals['nssf_total']);

        $byDepartment = app(SalarySummaryReport::class)->build(new ReportFilters($month->startOfMonth(), CarbonImmutable::today(), null, ['group' => 'department']), $approver);
        $this->assertSame((string) $run->total_gross, $byDepartment->totals['gross']);

        $salesOnly = app(SalarySummaryReport::class)->build(new ReportFilters($month->startOfMonth(), CarbonImmutable::today(), $sales->id), $approver);
        $this->assertCount(1, $salesOnly->rows);
        $this->assertSame('1000000.00', $salesOnly->rows[0]['gross']);

        $expenses = app(ExpensesReport::class)->build(new ReportFilters($month->startOfMonth(), CarbonImmutable::today(), null, ['category' => 'payroll']), $approver);
        $this->assertSame(['Payroll'], array_column($expenses->rows, 'category'));
        $this->assertSame((string) $run->total_employer_cost, $expenses->totals['amount']);
        $this->assertSame((string) $run->total_employer_cost, $expenses->summary[2]['value']);
    }

    public function test_requisitions_report_respects_visibility_and_groups_by_department(): void
    {
        $this->seed(ApprovalWorkflowSeeder::class);
        [$department, $head] = $this->departmentWithHead();
        [$otherDepartment] = $this->departmentWithHead();
        $requester = $this->userWithRole(SystemRole::GeneralEmployee);

        $this->submittedRequisition($requester, $department);
        $rejected = $this->submittedRequisition($requester, $department);
        $this->decide($rejected, $head, ApprovalDecision::Reject, 'Not budgeted');
        $this->submittedRequisition($requester, $otherDepartment);
        $this->draftRequisition($requester, $department);

        $mine = app(RequisitionsReport::class)->build($this->filters(), $head);
        $this->assertCount(2, $mine->rows, 'A department head sees only their department; drafts are never reported.');
        $this->assertSame(['Rejected'], array_values(array_filter(array_column($mine->rows, 'status'), fn (string $status): bool => $status === 'Rejected')));

        $everything = app(RequisitionsReport::class)->build($this->filters(options: ['group' => 'department']), $this->userWithRole(SystemRole::Manager));
        $this->assertSame(3, $everything->totals['total']);
        $this->assertSame(1, $everything->totals['rejected']);
        $this->assertSame('750000.00', $everything->totals['value']);

        $rejectedOnly = app(RequisitionsReport::class)->build($this->filters(options: ['status' => 'rejected']), $head);
        $this->assertCount(1, $rejectedOnly->rows);
    }

    public function test_the_dashboard_shows_each_widget_only_with_its_permissions(): void
    {
        Item::factory()->withMinimum('5')->create(['name' => 'Printer paper']);

        $this->actingAs($this->userWithRole(SystemRole::FinanceOfficer))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Collected this month')
            ->assertSee('Outstanding receivables')
            ->assertSee('Revenue vs expenses – last 12 months')
            ->assertSee('Top customers')
            ->assertSee('Payroll trend');

        $this->actingAs($this->userWithRole(SystemRole::HumanResourceOfficer))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Payroll trend')
            ->assertSee('Active employees')
            ->assertDontSee('Collected this month')
            ->assertDontSee('Stock value');

        $this->actingAs($this->userWithRole(SystemRole::InventoryOfficer))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Stock value')
            ->assertSee('Low-stock items')
            ->assertDontSee('Outstanding receivables')
            ->assertDontSee('Revenue vs expenses')
            ->assertDontSee('Payroll trend');

        $this->actingAs($this->userWithRole(SystemRole::GeneralEmployee))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Awaiting my approval')
            ->assertDontSee('Stock value')
            ->assertDontSee('Invoices issued')
            ->assertDontSee('data-chart', false);
    }

    public function test_the_dashboard_period_selector_changes_the_period(): void
    {
        $accountant = $this->userWithRole(SystemRole::Accountant);

        $this->actingAs($accountant)->get(route('dashboard', ['period' => 'year']))
            ->assertOk()->assertSee('Collected this year')->assertSee('Expenses this year');

        $this->actingAs($accountant)->get(route('dashboard', ['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-03-31']))
            ->assertOk()->assertSee('Collected in period')->assertSee('value="2026-01-01"', false);

        $this->actingAs($accountant)->get(route('dashboard', ['period' => 'custom', 'from' => '2026-03-31', 'to' => '2026-01-01']))
            ->assertOk()->assertSee('Collected this month');
    }

    public function test_recent_transactions_list_issued_invoices_and_stock_movements(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);
        $item = Item::factory()->create(['name' => 'Office chair']);
        app(StockService::class)->receive($item, Warehouse::factory()->create(['code' => 'MAIN']), '3', '90000', StockMovementType::GoodsReceived);

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Recent transactions')
            ->assertSee('Goods received: Office chair');
    }
}
