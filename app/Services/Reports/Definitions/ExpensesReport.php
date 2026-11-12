<?php

namespace App\Services\Reports\Definitions;

use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Reports\PayrollCosts;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;

/**
 * Recorded expenses in the period plus payroll as its own category (FR-RPT-01, FR-EXP-03).
 */
class ExpensesReport extends Report
{
    public const PAYROLL = 'payroll';

    public function __construct(private PayrollCosts $payrollCosts) {}

    public function key(): string
    {
        return 'expenses';
    }

    public function title(): string
    {
        return 'Expenses';
    }

    public function description(): string
    {
        return 'Recorded expenses by date, category and department, with approved payroll shown as the "Payroll" category.';
    }

    public function group(): string
    {
        return self::GROUP_FINANCIAL;
    }

    public function permission(): string
    {
        return 'reports.financial';
    }

    public function supportsDepartment(): bool
    {
        return true;
    }

    public function selectFilters(): array
    {
        $categories = ExpenseCategory::query()->orderBy('name')->pluck('name', 'id')->mapWithKeys(fn (string $name, int $id): array => [(string) $id => $name])->all();

        return ['category' => ['label' => 'Category', 'options' => $categories + [self::PAYROLL => 'Payroll']]];
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $category = $filters->option('category');
        $rows = [];

        if ($category !== self::PAYROLL) {
            $expenses = Expense::query()
                ->recorded()
                ->whereBetween('expense_date', $filters->dates())
                ->when($filters->departmentId, fn ($query, int $id) => $query->where('department_id', $id))
                ->when($category, fn ($query, string $id) => $query->where('expense_category_id', (int) $id))
                ->with(['category:id,name', 'department:id,name', 'supplier:id,name'])
                ->orderBy('expense_date')
                ->orderBy('id')
                ->get();

            foreach ($expenses as $expense) {
                $rows[] = [
                    'date' => $expense->expense_date->toDateString(),
                    'number' => $expense->displayNumber(),
                    'category' => $expense->category?->name,
                    'department' => $expense->department?->name,
                    'payee' => $expense->supplier?->name ?? $expense->payee_name,
                    'description' => $expense->description,
                    'amount' => $this->money($expense->amount),
                ];
            }
        }

        $payrollTotal = '0.00';

        if ($category === null || $category === self::PAYROLL) {
            $departmentName = $filters->departmentId !== null ? Department::query()->whereKey($filters->departmentId)->value('name') : null;

            foreach ($this->payrollCosts->between($filters->from, $filters->to, $filters->departmentId) as $cost) {
                $rows[] = [
                    'date' => $cost['date'],
                    'number' => $cost['run']->displayNumber(),
                    'category' => 'Payroll',
                    'department' => $departmentName ?? 'All departments',
                    'payee' => 'Employees ('.$cost['employees'].')',
                    'description' => 'Payroll '.$cost['run']->periodLabel().' – gross pay plus employer NSSF',
                    'amount' => $cost['amount'],
                ];
            }

            $payrollTotal = $this->sum(array_filter($rows, fn (array $row): bool => $row['category'] === 'Payroll'), 'amount');
        }

        usort($rows, fn (array $a, array $b): int => [$a['date'], $a['number']] <=> [$b['date'], $b['number']]);

        $total = $this->sum($rows, 'amount');
        $byCategory = collect($rows)->groupBy('category')->map(fn ($group, $name): array => ['label' => (string) $name, 'value' => $this->sum($group, 'amount')]);

        return new ReportResult(
            columns: [
                ReportColumn::date('date', 'Date'),
                ReportColumn::text('number', 'Number'),
                ReportColumn::text('category', 'Category'),
                ReportColumn::text('department', 'Department'),
                ReportColumn::text('payee', 'Payee'),
                ReportColumn::text('description', 'Description'),
                ReportColumn::money('amount', 'Amount'),
            ],
            rows: $rows,
            totals: ['payee' => 'Total ('.count($rows).')', 'amount' => $total],
            summary: [
                $this->stat('Total expenses', $total),
                $this->stat('Operating expenses', bcsub($total, $payrollTotal, 2)),
                $this->stat('Payroll', $payrollTotal),
            ],
            chart: $this->rankedChart($byCategory, 'Amount', 'doughnut'),
        );
    }
}
