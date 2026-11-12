<?php

namespace App\Services\Reports;

/**
 * The output of a report: columns, rows (keyed by column key), an optional totals row, summary figures and an
 * optional chart. Money values are decimal strings, dates are Y-m-d strings; formatting happens when shown.
 */
final class ReportResult
{
    /**
     * @param  list<ReportColumn>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     * @param  list<array{label: string, value: mixed, type: string}>  $summary
     * @param  array{type: string, labels: list<string>, datasets: list<array{label: string, data: list<float|int>}>, money?: bool}|null  $chart
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly array $columns,
        public array $rows,
        public readonly ?array $totals = null,
        public readonly array $summary = [],
        public readonly ?array $chart = null,
        public readonly array $notes = [],
    ) {}

    public function column(string $key): ?ReportColumn
    {
        foreach ($this->columns as $column) {
            if ($column->key === $key) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Sort rows by a column. Numeric columns compare as numbers, others as case-insensitive text.
     */
    public function sortBy(string $key, string $direction): void
    {
        $column = $this->column($key);

        if ($column === null || ! $column->sortable) {
            return;
        }

        $factor = $direction === 'desc' ? -1 : 1;

        usort($this->rows, function (array $a, array $b) use ($column, $factor): int {
            $left = $a[$column->key] ?? null;
            $right = $b[$column->key] ?? null;

            $result = $column->isNumeric()
                ? bccomp((string) ($left ?? '0'), (string) ($right ?? '0'), 4)
                : strnatcasecmp((string) $left, (string) $right);

            return $result * $factor;
        });
    }
}
