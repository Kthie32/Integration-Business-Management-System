<?php

namespace App\Services\Payroll;

use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Support\Money;
use InvalidArgumentException;

/**
 * The run-level documents of FR-PRL-08: the payroll register (summary per employee), the statutory deductions
 * schedule (PAYE, NSSF, LST) and the bank / mobile money payment list (docs/07 D7: a generic layout).
 * Each is returned as a title, headings, rows and a totals row, shared by the PDF and Excel exports.
 */
class PayrollReportService
{
    public const REPORTS = [
        'register' => 'Payroll register',
        'deductions' => 'Statutory deductions schedule',
        'payments' => 'Payment list',
    ];

    /**
     * @return array{title: string, headings: list<string>, rows: list<list<string>>, totals: list<string>, numeric_from: int}
     */
    public function build(PayrollRun $run, string $report): array
    {
        $items = $run->items()->orderBy('employee_name')->get();

        return match ($report) {
            'register' => $this->register($run, $items->all()),
            'deductions' => $this->deductions($run, $items->all()),
            'payments' => $this->payments($run, $items->all()),
            default => throw new InvalidArgumentException("Unknown payroll report {$report}."),
        };
    }

    /**
     * @param  list<PayrollItem>  $items
     * @return array{title: string, headings: list<string>, rows: list<list<string>>, totals: list<string>, numeric_from: int}
     */
    private function register(PayrollRun $run, array $items): array
    {
        $columns = ['basic_salary', 'total_allowances', 'gross_pay', 'paye', 'nssf_employee', 'lst', 'other_deductions', 'net_pay', 'nssf_employer'];

        return [
            'title' => self::REPORTS['register'].' – '.$run->periodLabel(),
            'headings' => ['Employee no.', 'Name', 'Department', 'Basic', 'Allowances', 'Gross pay', 'PAYE', 'NSSF 5%', 'LST', 'Other deductions', 'Net pay', 'NSSF employer'],
            'rows' => array_map(fn (PayrollItem $item): array => [
                $item->employee_number, $item->employee_name, (string) $item->department_name,
                ...array_map(fn (string $column): string => (string) $item->{$column}, $columns),
            ], $items),
            'totals' => ['', 'Total ('.count($items).')', '', ...$this->sums($items, $columns)],
            'numeric_from' => 3,
        ];
    }

    /**
     * @param  list<PayrollItem>  $items
     * @return array{title: string, headings: list<string>, rows: list<list<string>>, totals: list<string>, numeric_from: int}
     */
    private function deductions(PayrollRun $run, array $items): array
    {
        return [
            'title' => self::REPORTS['deductions'].' – '.$run->periodLabel(),
            'headings' => ['Employee no.', 'Name', 'TIN', 'NSSF no.', 'Gross pay', 'Taxable pay', 'PAYE', 'NSSF pay', 'NSSF employee', 'NSSF employer', 'NSSF total', 'LST'],
            'rows' => array_map(fn (PayrollItem $item): array => [
                $item->employee_number, $item->employee_name, (string) $item->tin, (string) $item->nssf_number,
                (string) $item->gross_pay, (string) $item->taxable_pay, (string) $item->paye, (string) $item->nssf_pay,
                (string) $item->nssf_employee, (string) $item->nssf_employer,
                Money::of((string) $item->nssf_employee)->plus((string) $item->nssf_employer)->toDecimal(),
                (string) $item->lst,
            ], $items),
            'totals' => [
                '', 'Total ('.count($items).')', '', '',
                ...$this->sums($items, ['gross_pay', 'taxable_pay', 'paye', 'nssf_pay', 'nssf_employee', 'nssf_employer']),
                Money::of((string) $run->total_nssf_employee)->plus((string) $run->total_nssf_employer)->toDecimal(),
                ...$this->sums($items, ['lst']),
            ],
            'numeric_from' => 4,
        ];
    }

    /**
     * @param  list<PayrollItem>  $items
     * @return array{title: string, headings: list<string>, rows: list<list<string>>, totals: list<string>, numeric_from: int}
     */
    private function payments(PayrollRun $run, array $items): array
    {
        return [
            'title' => self::REPORTS['payments'].' – '.$run->periodLabel(),
            'headings' => ['Employee no.', 'Name', 'Method', 'Bank', 'Account / number', 'Payslip', 'Net pay'],
            'rows' => array_map(fn (PayrollItem $item): array => [
                $item->employee_number, $item->employee_name, $item->payment_method->label(),
                (string) $item->bank_name,
                (string) ($item->bank_account ?? $item->mobile_money_number ?? ''),
                (string) $item->payslip_number,
                (string) $item->net_pay,
            ], $items),
            'totals' => ['', 'Total ('.count($items).')', '', '', '', '', ...$this->sums($items, ['net_pay'])],
            'numeric_from' => 6,
        ];
    }

    /**
     * @param  list<PayrollItem>  $items
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function sums(array $items, array $columns): array
    {
        return array_map(
            fn (string $column): string => Money::sum(array_map(fn (PayrollItem $item): string => (string) $item->{$column}, $items))->toDecimal(),
            $columns,
        );
    }
}
