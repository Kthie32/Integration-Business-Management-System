<?php

namespace App\Exports;

use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportResult;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Any report as an Excel sheet: headings, one row per report row and the totals row. Numbers and dates are written
 * as real spreadsheet values so they can be summed, filtered and formatted.
 */
class ReportExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithTitle
{
    public function __construct(private string $title, private ReportResult $result) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $rows = array_map(fn (array $row): array => $this->typed($row), $this->result->rows);

        if ($this->result->totals !== null) {
            $rows[] = $this->typed($this->result->totals, totals: true);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_map(fn (ReportColumn $column): string => $column->label, $this->result->columns);
    }

    public function title(): string
    {
        return mb_substr(trim((string) preg_replace('/[\\\\\/?*\[\]:]/', '', $this->title)) ?: 'Report', 0, 31);
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->result->columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);

            $format = match ($column->type) {
                ReportColumn::MONEY => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
                ReportColumn::NUMBER => '#,##0',
                ReportColumn::QUANTITY => '#,##0.###',
                ReportColumn::DATE => 'dd mmm yyyy',
                ReportColumn::PERCENT => '0.0%',
                default => null,
            };

            if ($format !== null) {
                $formats[$letter] = $format;
            }
        }

        return $formats;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    private function typed(array $row, bool $totals = false): array
    {
        $cells = [];

        foreach ($this->result->columns as $column) {
            $value = $row[$column->key] ?? null;

            $cells[] = match (true) {
                $value === null || $value === '' => null,
                $column->type === ReportColumn::DATE && ! $totals => Date::stringToExcel((string) $value) ?: (string) $value,
                $column->type === ReportColumn::PERCENT && is_numeric($value) => (float) $value / 100,
                $column->isNumeric() && is_numeric($value) => (float) $value,
                default => (string) $value,
            };
        }

        return $cells;
    }
}
