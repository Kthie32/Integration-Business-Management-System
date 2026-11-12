<?php

namespace App\Services\Reports\Definitions;

use App\Models\Department;
use App\Models\User;
use App\Services\Reports\PayrollCosts;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

/**
 * Salary summary by employee or by department over the approved runs in the period (FR-RPT-04). Department is the
 * one recorded on each payslip when the run was calculated.
 */
class SalarySummaryReport extends Report
{
    public function key(): string
    {
        return 'salary-summary';
    }

    public function title(): string
    {
        return 'Salary summary';
    }

    public function description(): string
    {
        return 'Gross pay, deductions and net pay per employee or per department across the approved payroll runs in the period.';
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

    public function selectFilters(): array
    {
        return ['group' => ['label' => 'Group by', 'options' => ['employee' => 'Employee', 'department' => 'Department']]];
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $byDepartment = $filters->option('group') === 'department';
        $departmentName = $filters->departmentId !== null ? Department::query()->whereKey($filters->departmentId)->value('name') : null;

        $query = DB::table('payroll_items')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
            ->whereIn('payroll_runs.status', PayrollCosts::releasedStatuses())
            ->whereRaw('(payroll_runs.period_year * 100 + payroll_runs.period_month) between ? and ?', [(int) $filters->from->format('Ym'), (int) $filters->to->format('Ym')])
            ->when($departmentName !== null, fn ($inner) => $inner->where('payroll_items.department_name', $departmentName));

        $sums = 'SUM(payroll_items.gross_pay) as gross, SUM(payroll_items.paye) as paye, SUM(payroll_items.nssf_employee) as nssf_employee, '
            .'SUM(payroll_items.lst) as lst, SUM(payroll_items.other_deductions) as other, SUM(payroll_items.total_deductions) as deductions, '
            .'SUM(payroll_items.net_pay) as net, SUM(payroll_items.nssf_employer) as nssf_employer';

        if ($byDepartment) {
            $groups = $query->groupBy('payroll_items.department_name')
                ->selectRaw("COALESCE(payroll_items.department_name, 'No department') as name, COUNT(DISTINCT payroll_items.employee_id) as employees, COUNT(*) as payslips, {$sums}")
                ->orderBy('name')
                ->get();
        } else {
            $groups = $query->groupBy('payroll_items.employee_id')
                ->selectRaw("MAX(payroll_items.employee_number) as employee_number, MAX(payroll_items.employee_name) as name, MAX(payroll_items.department_name) as department, COUNT(*) as payslips, {$sums}")
                ->orderBy('name')
                ->get();
        }

        $amountKeys = ['gross', 'paye', 'nssf_employee', 'lst', 'other', 'deductions', 'net', 'nssf_employer'];

        $rows = $groups->map(function (object $group) use ($byDepartment, $amountKeys): array {
            $row = $byDepartment
                ? ['name' => $group->name, 'employees' => (int) $group->employees, 'payslips' => (int) $group->payslips]
                : ['employee_number' => $group->employee_number, 'name' => $group->name, 'department' => $group->department, 'payslips' => (int) $group->payslips];

            foreach ($amountKeys as $key) {
                $row[$key] = $this->money($group->{$key});
            }

            return $row;
        })->all();

        $totals = ['name' => 'Total ('.count($rows).')', 'payslips' => array_sum(array_column($rows, 'payslips'))];

        foreach ($amountKeys as $key) {
            $totals[$key] = $this->sum($rows, $key);
        }

        $leading = $byDepartment
            ? [ReportColumn::text('name', 'Department'), ReportColumn::number('employees', 'Employees')]
            : [ReportColumn::text('employee_number', 'Employee no.'), ReportColumn::text('name', 'Employee'), ReportColumn::text('department', 'Department')];

        return new ReportResult(
            columns: [
                ...$leading,
                ReportColumn::number('payslips', 'Payslips'),
                ReportColumn::money('gross', 'Gross pay'),
                ReportColumn::money('paye', 'PAYE'),
                ReportColumn::money('nssf_employee', 'NSSF 5%'),
                ReportColumn::money('lst', 'LST'),
                ReportColumn::money('other', 'Other'),
                ReportColumn::money('deductions', 'Total deductions'),
                ReportColumn::money('net', 'Net pay'),
                ReportColumn::money('nssf_employer', 'NSSF 10%'),
            ],
            rows: $rows,
            totals: $totals,
            summary: [
                $this->stat('Gross pay', $totals['gross']),
                $this->stat('Total deductions', $totals['deductions']),
                $this->stat('Net pay', $totals['net']),
            ],
            chart: $this->rankedChart(collect($rows)->map(fn (array $row): array => ['label' => (string) $row['name'], 'value' => $row['gross']]), 'Gross pay'),
        );
    }
}
