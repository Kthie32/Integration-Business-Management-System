<?php

namespace App\Services\Payroll;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * An Excel sheet for one payroll report built by PayrollReportService. Amount columns are written as numbers so
 * they can be summed and formatted in the spreadsheet.
 */
class PayrollReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  array{title: string, headings: list<string>, rows: list<list<string>>, totals: list<string>, numeric_from: int}  $report
     */
    public function __construct(private array $report) {}

    /**
     * @return array<int, array<int, string|float>>
     */
    public function array(): array
    {
        return array_map(fn (array $row): array => $this->typed($row), [...$this->report['rows'], $this->report['totals']]);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->report['headings'];
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $this->report['title']) ?? 'Payroll', 0, 31);
    }

    /**
     * @param  list<string>  $row
     * @return array<int, string|float>
     */
    private function typed(array $row): array
    {
        foreach ($row as $index => $value) {
            if ($index >= $this->report['numeric_from'] && is_numeric($value)) {
                $row[$index] = (float) $value;
            }
        }

        return $row;
    }
}
