<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Reports\Definitions\ExpensesReport;
use App\Services\Reports\Definitions\InvoiceRegisterReport;
use App\Services\Reports\Definitions\LowStockReport;
use App\Services\Reports\Definitions\PaymentsByMethodReport;
use App\Services\Reports\Definitions\PayrollSummaryReport;
use App\Services\Reports\Definitions\QuotationSummaryReport;
use App\Services\Reports\Definitions\ReceiptRegisterReport;
use App\Services\Reports\Definitions\ReceivablesAgingReport;
use App\Services\Reports\Definitions\RequisitionsReport;
use App\Services\Reports\Definitions\RevenueReport;
use App\Services\Reports\Definitions\RevenueVsExpensesReport;
use App\Services\Reports\Definitions\SalarySummaryReport;
use App\Services\Reports\Definitions\SalesByCustomerReport;
use App\Services\Reports\Definitions\SalesByItemReport;
use App\Services\Reports\Definitions\StatutoryDeductionsReport;
use App\Services\Reports\Definitions\StockAdjustmentsReport;
use App\Services\Reports\Definitions\StockBalancesReport;
use App\Services\Reports\Definitions\StockIssuedReport;
use App\Services\Reports\Definitions\StockReceivedReport;

/**
 * Every report in the Reports hub, plus links to the per-record reports that live on their own pages
 * (customer statement, item stock card, payroll run register).
 */
class ReportRegistry
{
    /**
     * @var list<class-string<Report>>
     */
    public const REPORTS = [
        RevenueReport::class,
        PaymentsByMethodReport::class,
        ExpensesReport::class,
        RevenueVsExpensesReport::class,
        ReceivablesAgingReport::class,
        InvoiceRegisterReport::class,
        ReceiptRegisterReport::class,
        QuotationSummaryReport::class,
        SalesByCustomerReport::class,
        SalesByItemReport::class,
        StockBalancesReport::class,
        StockReceivedReport::class,
        StockIssuedReport::class,
        StockAdjustmentsReport::class,
        LowStockReport::class,
        PayrollSummaryReport::class,
        SalarySummaryReport::class,
        StatutoryDeductionsReport::class,
        RequisitionsReport::class,
    ];

    /**
     * @return list<Report>
     */
    public function all(): array
    {
        return array_map(fn (string $class): Report => app($class), self::REPORTS);
    }

    public function find(string $key): ?Report
    {
        foreach ($this->all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        return null;
    }

    /**
     * The hub for a user: groups of runnable reports and related per-record report pages.
     *
     * @return array<string, list<array{title: string, description: string, url: string}>>
     */
    public function hubFor(User $user): array
    {
        $groups = [];

        foreach ($this->all() as $report) {
            if ($report->authorize($user)) {
                $groups[$report->group()][] = [
                    'title' => $report->title(),
                    'description' => $report->description(),
                    'url' => route('reports.show', $report->key()),
                ];
            }
        }

        foreach ($this->relatedPagesFor($user) as $group => $page) {
            $groups[$group][] = $page;
        }

        $order = [Report::GROUP_FINANCIAL, Report::GROUP_SALES, Report::GROUP_INVENTORY, Report::GROUP_PAYROLL, Report::GROUP_REQUISITIONS];

        return array_filter(array_merge(array_fill_keys($order, []), $groups));
    }

    /**
     * Reports that need a record chosen first; shown when the user can open both the report and the page.
     *
     * @return \Generator<string, array{title: string, description: string, url: string}>
     */
    private function relatedPagesFor(User $user): \Generator
    {
        if ($user->can('reports.financial') && $user->can('customers.view') && $user->can('invoices.view') && $user->can('payments.view')) {
            yield Report::GROUP_FINANCIAL => [
                'title' => 'Customer statement',
                'description' => 'Choose a customer, then open "Statement" for a running statement of account with PDF and print.',
                'url' => route('customers.index'),
            ];
        }

        if ($user->can('reports.inventory') && $user->can('items.view') && $user->can('stock.view')) {
            yield Report::GROUP_INVENTORY => [
                'title' => 'Item stock card',
                'description' => 'Choose an item, then open its stock card for opening, movements and running balance by warehouse.',
                'url' => route('items.index'),
            ];
        }

        if ($user->can('reports.payroll') && $user->can('payroll.view')) {
            yield Report::GROUP_PAYROLL => [
                'title' => 'Monthly payroll register',
                'description' => 'Open a payroll run for its register, statutory schedule and payment list as PDF, Excel or print.',
                'url' => route('payroll-runs.index'),
            ];
        }
    }
}
