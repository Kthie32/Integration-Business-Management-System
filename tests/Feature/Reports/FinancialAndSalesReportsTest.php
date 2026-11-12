<?php

namespace Tests\Feature\Reports;

use App\Enums\QuotationStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Reports\Definitions\ExpensesReport;
use App\Services\Reports\Definitions\InvoiceRegisterReport;
use App\Services\Reports\Definitions\PaymentsByMethodReport;
use App\Services\Reports\Definitions\QuotationSummaryReport;
use App\Services\Reports\Definitions\ReceivablesAgingReport;
use App\Services\Reports\Definitions\RevenueReport;
use App\Services\Reports\Definitions\RevenueVsExpensesReport;
use App\Services\Reports\Definitions\SalesByCustomerReport;
use App\Services\Reports\Definitions\SalesByItemReport;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Financial and sales reports (FR-RPT-01, FR-RPT-02) against a known set of invoices, payments and expenses.
 */
class FinancialAndSalesReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    private Customer $acme;

    private Customer $bolt;

    private Invoice $acmeInvoice;

    private Invoice $boltInvoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
        $this->acme = Customer::factory()->create(['name' => 'Acme Uganda Ltd']);
        $this->bolt = Customer::factory()->create(['name' => 'Bolt Traders']);
        $payments = app(PaymentService::class);

        // Acme: 500,000 dated 70 days ago, due 40 days ago; paid 200,000 by bank and 50,000 cash → 250,000 in 31–60.
        $this->acmeInvoice = $this->issueInvoice($this->acme, 70, 40, '500000', 'Consulting');
        $payments->record($this->acmeInvoice, $this->payment(30, '200000', 'bank_transfer', 'BANK-1'), $this->financeOfficer);
        $payments->record($this->acmeInvoice, $this->payment(20, '50000', 'cash', null), $this->financeOfficer);

        // Bolt: 300,000 dated 10 days ago, not yet due; a 100,000 payment that was reversed.
        $this->boltInvoice = $this->issueInvoice($this->bolt, 10, -20, '300000', 'Printer toner');
        $payments->reverse($payments->record($this->boltInvoice, $this->payment(5, '100000', 'mobile_money', 'MM-1'), $this->financeOfficer), $this->financeOfficer, 'Wrong account');

        // A void invoice is listed in the register but never counted.
        app(InvoiceService::class)->void($this->issueInvoice($this->bolt, 8, -22, '75000', 'Consulting'), $this->financeOfficer, 'Raised in error');
    }

    private function issueInvoice(Customer $customer, int $daysAgo, int $dueDaysAgo, string $price, string $description): Invoice
    {
        $invoices = app(InvoiceService::class);

        return $invoices->issue($invoices->createDraft([
            'customer_id' => $customer->id,
            'invoice_date' => today()->subDays($daysAgo)->toDateString(),
            'due_date' => today()->subDays($dueDaysAgo)->toDateString(),
            'lines' => [['description' => $description, 'quantity' => '1', 'unit_price' => $price]],
        ], $this->financeOfficer), $this->financeOfficer);
    }

    /**
     * @return array<string, string|null>
     */
    private function payment(int $daysAgo, string $amount, string $method, ?string $reference): array
    {
        return ['payment_date' => today()->subDays($daysAgo)->toDateString(), 'amount' => $amount, 'method' => $method, 'reference' => $reference];
    }

    /**
     * @param  array<string, string>  $options
     */
    private function filters(int $fromDaysAgo = 90, array $options = [], ?int $departmentId = null): ReportFilters
    {
        return new ReportFilters(CarbonImmutable::today()->subDays($fromDaysAgo), CarbonImmutable::today(), $departmentId, $options);
    }

    /**
     * @param  class-string  $class
     */
    private function build(string $class, ?ReportFilters $filters = null): ReportResult
    {
        return app($class)->build($filters ?? $this->filters(), $this->financeOfficer);
    }

    public function test_revenue_counts_completed_payments_only(): void
    {
        $result = $this->build(RevenueReport::class);

        $this->assertSame(['200000.00', '50000.00'], array_column($result->rows, 'amount'));
        $this->assertSame('250000.00', $result->totals['amount']);
        $this->assertSame('Bank transfer', $result->rows[0]['method']);

        $cashOnly = $this->build(RevenueReport::class, $this->filters(options: ['method' => 'cash']));
        $this->assertSame('50000.00', $cashOnly->totals['amount']);

        $lastWeek = $this->build(RevenueReport::class, $this->filters(7));
        $this->assertSame([], $lastWeek->rows);
    }

    public function test_payments_by_method_shows_amounts_and_shares(): void
    {
        $result = $this->build(PaymentsByMethodReport::class);

        $this->assertSame([
            ['method' => 'Cash', 'payments' => 1, 'amount' => '50000.00', 'share' => '20.0'],
            ['method' => 'Bank transfer', 'payments' => 1, 'amount' => '200000.00', 'share' => '80.0'],
        ], $result->rows);
        $this->assertSame('250000.00', $result->totals['amount']);
    }

    public function test_receivables_ageing_buckets_open_balances_by_days_past_due(): void
    {
        $result = $this->build(ReceivablesAgingReport::class);
        $rows = collect($result->rows)->keyBy('customer');

        $this->assertSame('250000.00', $rows['Acme Uganda Ltd']['days_31_60']);
        $this->assertSame('0.00', $rows['Acme Uganda Ltd']['current']);
        $this->assertSame('300000.00', $rows['Bolt Traders']['current']);
        $this->assertSame('550000.00', $result->totals['total']);
        $this->assertSame('250000.00', $result->summary[1]['value']);
        $this->assertSame('days_over_90', ReceivablesAgingReport::bucketFor(91));
        $this->assertSame('days_61_90', ReceivablesAgingReport::bucketFor(90));
    }

    public function test_invoice_register_lists_void_invoices_without_counting_them(): void
    {
        $result = $this->build(InvoiceRegisterReport::class);

        $this->assertCount(3, $result->rows);
        $this->assertSame('800000.00', $result->totals['total']);
        $this->assertSame('250000.00', $result->totals['paid']);
        $this->assertSame('550000.00', $result->totals['balance']);

        $void = $this->build(InvoiceRegisterReport::class, $this->filters(options: ['status' => 'void']));
        $this->assertCount(1, $void->rows);
        $this->assertSame('Void', $void->rows[0]['status']);
    }

    public function test_sales_by_customer_and_item_exclude_void_invoices(): void
    {
        $customers = $this->build(SalesByCustomerReport::class);
        $this->assertSame(['Acme Uganda Ltd', 'Bolt Traders'], array_column($customers->rows, 'customer'));
        $this->assertSame(['500000.00', '300000.00'], array_column($customers->rows, 'total'));
        $this->assertSame('62.5', $customers->rows[0]['share']);

        $items = $this->build(SalesByItemReport::class);
        $this->assertSame(['Consulting' => '500000.00', 'Printer toner' => '300000.00'], array_column($items->rows, 'total', 'item'));
    }

    public function test_expenses_and_revenue_vs_expenses(): void
    {
        $fuel = ExpenseCategory::factory()->create(['name' => 'Fuel']);
        Expense::factory()->recorded()->create(['expense_category_id' => $fuel->id, 'amount' => '120000.00', 'expense_date' => today()->subDays(3)]);
        Expense::factory()->create(['expense_category_id' => $fuel->id, 'amount' => '999000.00', 'expense_date' => today()->subDays(3)]);

        $expenses = $this->build(ExpensesReport::class);
        $this->assertCount(1, $expenses->rows);
        $this->assertSame('120000.00', $expenses->totals['amount']);
        $this->assertSame('0.00', $expenses->summary[2]['value']);

        $monthly = $this->build(RevenueVsExpensesReport::class, new ReportFilters(CarbonImmutable::today()->subMonths(3)->startOfMonth(), CarbonImmutable::today()));
        $this->assertSame('250000.00', $monthly->totals['revenue']);
        $this->assertSame('120000.00', $monthly->totals['total_expenses']);
        $this->assertSame('130000.00', $monthly->totals['net']);
    }

    public function test_quotation_summary_counts_statuses_and_conversion_rate(): void
    {
        foreach ([QuotationStatus::Accepted, QuotationStatus::Accepted, QuotationStatus::Rejected, QuotationStatus::Sent, QuotationStatus::Cancelled, QuotationStatus::Superseded] as $status) {
            Quotation::factory()->status($status)->create(['customer_id' => $this->acme->id, 'quote_date' => today()->subDays(5), 'total' => '100000.00']);
        }

        Quotation::factory()->create(['customer_id' => $this->acme->id]);

        $result = $this->build(QuotationSummaryReport::class);
        $summary = array_column($result->summary, 'value', 'label');

        $this->assertSame(5, $summary['Generated']);
        $this->assertSame(2, $summary['Accepted']);
        $this->assertSame(1, $summary['Rejected']);
        $this->assertSame(1, $summary['Pending']);
        $this->assertSame('50.0', $summary['Conversion rate']);

        $accepted = $this->build(QuotationSummaryReport::class, $this->filters(options: ['status' => 'accepted']));
        $this->assertCount(2, $accepted->rows);
    }

    public function test_report_page_sorts_and_validates_filters(): void
    {
        $url = route('reports.show', ['revenue', 'from' => today()->subDays(90)->toDateString(), 'to' => today()->toDateString(), 'sort' => 'amount', 'direction' => 'asc']);

        $this->actingAs($this->financeOfficer)->get($url)
            ->assertOk()
            ->assertSee('Revenue (payments received)')
            ->assertSeeInOrder(['UGX 50,000', 'UGX 200,000', 'UGX 250,000']);

        $this->actingAs($this->financeOfficer)
            ->from(route('reports.index'))
            ->get(route('reports.show', ['revenue', 'from' => '2026-05-10', 'to' => '2026-05-01']))
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors('to');

        $this->actingAs($this->financeOfficer)->get(route('reports.show', ['revenue', 'method' => 'barter']))->assertSessionHasErrors('method');
        $this->actingAs($this->financeOfficer)->get(route('reports.show', 'no-such-report'))->assertNotFound();
    }

    public function test_pdf_excel_and_print_exports_are_produced_and_downloads_audited(): void
    {
        $query = ['from' => today()->subDays(90)->toDateString(), 'to' => today()->toDateString()];

        $pdf = $this->actingAs($this->financeOfficer)->get(route('reports.export', ['revenue', 'pdf', ...$query]));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());

        $xlsx = $this->actingAs($this->financeOfficer)->get(route('reports.export', ['receivables-aging', 'xlsx']));
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', (string) file_get_contents($xlsx->getFile()->getPathname()));

        $this->actingAs($this->financeOfficer)->get(route('reports.export', ['invoice-register', 'print', ...$query]))
            ->assertOk()->assertSee('Invoice register')->assertSee('Acme Uganda Ltd')->assertSee('window.print()', false);

        $this->assertSame(2, AuditLog::query()->where('event', 'report_exported')->where('user_id', $this->financeOfficer->id)->count());
    }

    /**
     * docs/05 report permissions: role => one report per group it may run.
     *
     * @return array<string, array{SystemRole, list<string>}>
     */
    public static function roleReports(): array
    {
        $all = ['revenue', 'quotations', 'stock-balances', 'payroll-summary', 'requisitions'];

        return [
            'super administrator' => [SystemRole::SuperAdministrator, $all],
            'system administrator' => [SystemRole::SystemAdministrator, []],
            'manager' => [SystemRole::Manager, $all],
            'finance officer' => [SystemRole::FinanceOfficer, ['revenue', 'quotations', 'payroll-summary']],
            'accountant' => [SystemRole::Accountant, ['revenue', 'quotations']],
            'human resource officer' => [SystemRole::HumanResourceOfficer, ['payroll-summary']],
            'procurement officer' => [SystemRole::ProcurementOfficer, ['stock-balances', 'requisitions']],
            'inventory officer' => [SystemRole::InventoryOfficer, ['stock-balances']],
            'department head' => [SystemRole::DepartmentHead, ['requisitions']],
            'general employee' => [SystemRole::GeneralEmployee, []],
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    #[DataProvider('roleReports')]
    public function test_each_role_runs_only_its_reports(SystemRole $role, array $allowed): void
    {
        $user = $this->userWithRole($role);

        foreach (['revenue', 'quotations', 'stock-balances', 'payroll-summary', 'requisitions'] as $report) {
            $response = $this->actingAs($user)->get(route('reports.show', $report));
            in_array($report, $allowed, true) ? $response->assertOk() : $response->assertForbidden();
        }

        $hub = $this->actingAs($user)->get(route('reports.index'));

        if ($allowed === []) {
            $hub->assertForbidden();

            return;
        }

        $hub->assertOk();
        in_array('revenue', $allowed, true) ? $hub->assertSee('Receivables ageing') : $hub->assertDontSee('Receivables ageing');
        in_array('stock-balances', $allowed, true) ? $hub->assertSee('Low stock') : $hub->assertDontSee('Low stock');
    }

    public function test_downloads_need_the_export_permission_but_print_does_not(): void
    {
        $head = $this->userWithRole(SystemRole::DepartmentHead);

        $this->actingAs($head)->get(route('reports.show', 'requisitions'))->assertOk()->assertDontSee('bi-filetype-pdf', false);
        $this->actingAs($head)->get(route('reports.export', ['requisitions', 'print']))->assertOk();
        $this->actingAs($head)->get(route('reports.export', ['requisitions', 'pdf']))->assertForbidden();
        $this->actingAs($head)->get(route('reports.export', ['requisitions', 'xlsx']))->assertForbidden();
        $this->actingAs($head)->get(route('reports.export', ['revenue', 'print']))->assertForbidden();
    }
}
