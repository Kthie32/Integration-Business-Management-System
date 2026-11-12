<?php

namespace App\Services\Reports\Definitions;

use App\Models\Department;
use App\Models\User;
use App\Services\Reports\PayrollCosts;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * PAYE, NSSF and LST per month over the approved runs in the period (FR-RPT-04).
 */
class StatutoryDeductionsReport extends Report
{
    public function key(): string
    {
        return 'statutory-deductions';
    }

    public function title(): string
    {
        return 'Statutory deductions';
    }

    public function description(): string
    {
        return 'PAYE, NSSF (employee 5% and employer 10%) and LST due for each approved payroll month in the period.';
    }

    public function group(): string
    {
        return self::GROUP_PAYROLL;
    }

    public function permission(): string
    {
        return 'reports.payroll';
    }

    public function supportsDepartment(): bool
    {
        return true;
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $departmentName = $filters->departmentId !== null ? Department::query()->whereKey($filters->departmentId)->value('name') : null;

        $groups = DB::table('payroll_items')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
            ->whereIn('payroll_runs.status', PayrollCosts::releasedStatuses())
            ->whereRaw('(payroll_runs.period_year * 100 + payroll_runs.period_month) between ? and ?', [(int) $filters->from->format('Ym'), (int) $filters->to->format('Ym')])
            ->when($departmentName !== null, fn ($query) => $query->where('payroll_items.department_name', $departmentName))
            ->groupBy('payroll_runs.id', 'payroll_runs.number', 'payroll_runs.period_year', 'payroll_runs.period_month')
            ->selectRaw('payroll_runs.number, payroll_runs.period_year, payroll_runs.period_month, COUNT(*) as employees, SUM(payroll_items.gross_pay) as gross, '
                .'SUM(payroll_items.paye) as paye, SUM(payroll_items.nssf_employee) as nssf_employee, SUM(payroll_items.nssf_employer) as nssf_employer, SUM(payroll_items.lst) as lst')
            ->orderBy('payroll_runs.period_year')
            ->orderBy('payroll_runs.period_month')
            ->get();

        $rows = $groups->map(fn (object $group): array => [
            'period' => CarbonImmutable::create((int) $group->period_year, (int) $group->period_month, 1)->format('F Y'),
            'number' => $group->number,
            'employees' => (int) $group->employees,
            'gross' => $this->money($group->gross),
            'paye' => $this->money($group->paye),
            'nssf_employee' => $this->money($group->nssf_employee),
            'nssf_employer' => $this->money($group->nssf_employer),
            'nssf_total' => bcadd($this->money($group->nssf_employee), $this->money($group->nssf_employer), 2),
            'lst' => $this->money($group->lst),
            'total' => bcadd(bcadd($this->money($group->paye), bcadd($this->money($group->nssf_employee), $this->money($group->nssf_employer), 2), 2), $this->money($group->lst), 2),
        ])->all();

        $totals = ['period' => 'Total'];

        foreach (['gross', 'paye', 'nssf_employee', 'nssf_employer', 'nssf_total', 'lst', 'total'] as $key) {
            $totals[$key] = $this->sum($rows, $key);
        }

        return new ReportResult(
            columns: [
                new ReportColumn('period', 'Period', ReportColumn::TEXT, false),
                ReportColumn::text('number', 'Run'),
                ReportColumn::number('employees', 'Employees'),
                ReportColumn::money('gross', 'Gross pay'),
                ReportColumn::money('paye', 'PAYE'),
                ReportColumn::money('nssf_employee', 'NSSF 5%'),
                ReportColumn::money('nssf_employer', 'NSSF 10%'),
                ReportColumn::money('nssf_total', 'NSSF total'),
                ReportColumn::money('lst', 'LST'),
                ReportColumn::money('total', 'Total due'),
            ],
            rows: $rows,
            totals: $totals,
            summary: [
                $this->stat('PAYE', $totals['paye']),
                $this->stat('NSSF (15%)', $totals['nssf_total']),
                $this->stat('LST', $totals['lst']),
                $this->stat('Total due', $totals['total']),
            ],
            chart: [
                'type' => 'bar',
                'labels' => array_column($rows, 'period'),
                'datasets' => [
                    ['label' => 'PAYE', 'data' => array_map(fn (array $row): float => (float) $row['paye'], $rows)],
                    ['label' => 'NSSF', 'data' => array_map(fn (array $row): float => (float) $row['nssf_total'], $rows)],
                    ['label' => 'LST', 'data' => array_map(fn (array $row): float => (float) $row['lst'], $rows)],
                ],
                'money' => true,
            ],
        );
    }
}
