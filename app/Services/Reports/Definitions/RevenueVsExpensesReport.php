<?php

namespace App\Services\Reports\Definitions;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use App\Services\Reports\PayrollCosts;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use App\Support\Money;
use Carbon\CarbonPeriod;

/**
 * Month-by-month cash revenue against recorded expenses and payroll, with the net result (FR-DSH-02 as a report).
 */
class RevenueVsExpensesReport extends Report
{
    public function __construct(private PayrollCosts $payrollCosts) {}

    public function key(): string
    {
        return 'revenue-vs-expenses';
    }

    public function title(): string
    {
        return 'Revenue vs expenses';
    }

    public function description(): string
    {
        return 'Monthly payments received against recorded expenses and payroll, with the net result for each month.';
    }

    public function group(): string
    {
        return self::GROUP_FINANCIAL;
    }

    public function permission(): string
    {
        return 'reports.financial';
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $months = [];

        foreach (CarbonPeriod::create($filters->from->startOfMonth(), '1 month', $filters->to) as $month) {
            $months[$month->format('Y-m')] = ['month' => $month->format('M Y'), 'revenue' => Money::zero(), 'expenses' => Money::zero(), 'payroll' => Money::zero()];
        }

        $revenue = Payment::query()->completed()->whereBetween('payment_date', $filters->dates())
            ->get(['payment_date', 'amount']);

        foreach ($revenue as $payment) {
            $key = $payment->payment_date->format('Y-m');
            $months[$key]['revenue'] = $months[$key]['revenue']->plus((string) $payment->amount);
        }

        $expenses = Expense::query()->recorded()->whereBetween('expense_date', $filters->dates())
            ->get(['expense_date', 'amount']);

        foreach ($expenses as $expense) {
            $key = $expense->expense_date->format('Y-m');
            $months[$key]['expenses'] = $months[$key]['expenses']->plus((string) $expense->amount);
        }

        foreach ($this->payrollCosts->between($filters->from, $filters->to) as $cost) {
            $key = substr($cost['date'], 0, 7);

            if (isset($months[$key])) {
                $months[$key]['payroll'] = $months[$key]['payroll']->plus($cost['amount']);
            }
        }

        $rows = array_values(array_map(function (array $month): array {
            $totalExpenses = $month['expenses']->plus($month['payroll']);

            return [
                'month' => $month['month'],
                'revenue' => $month['revenue']->toDecimal(),
                'expenses' => $month['expenses']->toDecimal(),
                'payroll' => $month['payroll']->toDecimal(),
                'total_expenses' => $totalExpenses->toDecimal(),
                'net' => $month['revenue']->minus($totalExpenses)->toDecimal(),
            ];
        }, $months));

        $totals = ['month' => 'Total'];

        foreach (['revenue', 'expenses', 'payroll', 'total_expenses', 'net'] as $key) {
            $totals[$key] = $this->sum($rows, $key);
        }

        return new ReportResult(
            columns: [
                new ReportColumn('month', 'Month', ReportColumn::TEXT, false),
                ReportColumn::money('revenue', 'Revenue'),
                ReportColumn::money('expenses', 'Expenses'),
                ReportColumn::money('payroll', 'Payroll'),
                ReportColumn::money('total_expenses', 'Total expenses'),
                ReportColumn::money('net', 'Net'),
            ],
            rows: $rows,
            totals: $totals,
            summary: [
                $this->stat('Revenue', $totals['revenue']),
                $this->stat('Total expenses', $totals['total_expenses']),
                $this->stat('Net', $totals['net']),
            ],
            chart: [
                'type' => 'bar',
                'labels' => array_column($rows, 'month'),
                'datasets' => [
                    ['label' => 'Revenue', 'data' => array_map(fn (array $row): float => (float) $row['revenue'], $rows)],
                    ['label' => 'Total expenses', 'data' => array_map(fn (array $row): float => (float) $row['total_expenses'], $rows)],
                ],
                'money' => true,
            ],
            notes: ['Revenue is cash received (completed payments). Payroll is gross pay plus employer NSSF of approved runs, counted in the pay period month.'],
        );
    }
}
