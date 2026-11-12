<?php

namespace App\Services\Reports\Definitions;

use App\Enums\PayrollRunStatus;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;

/**
 * Monthly payroll summary with payment status (FR-RPT-04): one row per approved or paid run whose pay period
 * falls in the selected months.
 */
class PayrollSummaryReport extends Report
{
    public function key(): string
    {
        return 'payroll-summary';
    }

    public function title(): string
    {
        return 'Payroll summary & payment status';
    }

    public function description(): string
    {
        return 'Totals of each approved payroll run in the period (gross, PAYE, NSSF, LST, net) and whether it has been paid.';
    }

    public function group(): string
    {
        return self::GROUP_PAYROLL;
    }

    public function permission(): string
    {
        return 'reports.payroll';
    }

    public function selectFilters(): array
    {
        return ['status' => ['label' => 'Payment status', 'options' => [
            PayrollRunStatus::Approved->value => 'Approved, not yet paid',
            PayrollRunStatus::Paid->value => 'Paid',
        ]]];
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $runs = PayrollRun::query()
            ->released()
            ->whereRaw('(period_year * 100 + period_month) between ? and ?', [(int) $filters->from->format('Ym'), (int) $filters->to->format('Ym')])
            ->when($filters->option('status'), fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        $rows = $runs->map(fn (PayrollRun $run): array => [
            'period' => $run->periodLabel(),
            'number' => $run->displayNumber(),
            'status' => $run->status === PayrollRunStatus::Paid ? 'Paid' : 'Approved, not yet paid',
            'employees' => $run->employee_count,
            'gross' => $this->money($run->total_gross),
            'paye' => $this->money($run->total_paye),
            'nssf_employee' => $this->money($run->total_nssf_employee),
            'lst' => $this->money($run->total_lst),
            'other' => $this->money($run->total_other_deductions),
            'net' => $this->money($run->total_net),
            'nssf_employer' => $this->money($run->total_nssf_employer),
            'paid_on' => $run->paid_at?->toDateString(),
        ])->all();

        $totals = ['period' => 'Total ('.count($rows).' runs)'];

        foreach (['gross', 'paye', 'nssf_employee', 'lst', 'other', 'net', 'nssf_employer'] as $key) {
            $totals[$key] = $this->sum($rows, $key);
        }

        $unpaid = $runs->filter(fn (PayrollRun $run): bool => $run->status === PayrollRunStatus::Approved);

        return new ReportResult(
            columns: [
                new ReportColumn('period', 'Period', ReportColumn::TEXT, false),
                ReportColumn::text('number', 'Run'),
                ReportColumn::text('status', 'Payment status'),
                ReportColumn::number('employees', 'Employees'),
                ReportColumn::money('gross', 'Gross pay'),
                ReportColumn::money('paye', 'PAYE'),
                ReportColumn::money('nssf_employee', 'NSSF 5%'),
                ReportColumn::money('lst', 'LST'),
                ReportColumn::money('other', 'Other deductions'),
                ReportColumn::money('net', 'Net pay'),
                ReportColumn::money('nssf_employer', 'NSSF 10%'),
                ReportColumn::date('paid_on', 'Paid on'),
            ],
            rows: $rows,
            totals: $totals,
            summary: [
                $this->stat('Gross pay', $totals['gross']),
                $this->stat('Net pay', $totals['net']),
                $this->stat('Employer cost', bcadd($totals['gross'], $totals['nssf_employer'], 2)),
                $this->stat('Runs awaiting payment', $unpaid->count(), ReportColumn::NUMBER),
            ],
            chart: [
                'type' => 'line',
                'labels' => array_column($rows, 'period'),
                'datasets' => [
                    ['label' => 'Gross pay', 'data' => array_map(fn (array $row): float => (float) $row['gross'], $rows)],
                    ['label' => 'Net pay', 'data' => array_map(fn (array $row): float => (float) $row['net'], $rows)],
                ],
                'money' => true,
            ],
        );
    }
}
