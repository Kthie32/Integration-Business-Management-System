<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * A report of FR-RPT-01…06: a key, title, group, gating permission, the filters it accepts and the query that
 * builds its result. The controller, PDF, Excel and print views are shared by every report.
 */
abstract class Report
{
    public const GROUP_FINANCIAL = 'Financial';

    public const GROUP_SALES = 'Quotations & sales';

    public const GROUP_INVENTORY = 'Inventory';

    public const GROUP_PAYROLL = 'Payroll';

    public const GROUP_REQUISITIONS = 'Requisitions';

    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    abstract public function group(): string;

    /**
     * The permission that runs the report (FR-RPT-06: each report gated by its own permission).
     */
    abstract public function permission(): string;

    abstract public function build(ReportFilters $filters, User $user): ReportResult;

    public function authorize(User $user): bool
    {
        return $user->can($this->permission());
    }

    /**
     * Whether the report is limited to a date range. Reports "as at today" (balances, ageing) return false.
     */
    public function usesPeriod(): bool
    {
        return true;
    }

    public function supportsDepartment(): bool
    {
        return false;
    }

    /**
     * Extra select filters: name => [label, options (value => label)].
     *
     * @return array<string, array{label: string, options: array<string, string>}>
     */
    public function selectFilters(): array
    {
        return [];
    }

    /**
     * A sub-title describing the filters, shown on screen and on exports.
     */
    public function describeFilters(ReportFilters $filters, string $periodText, ?string $departmentName): string
    {
        $parts = [$this->usesPeriod() ? $periodText : 'As at '.CarbonImmutable::today()->format('d M Y')];

        if ($departmentName !== null) {
            $parts[] = 'Department: '.$departmentName;
        }

        foreach ($this->selectFilters() as $name => $filter) {
            $value = $filters->option($name);

            if ($value !== null && isset($filter['options'][$value])) {
                $parts[] = $filter['label'].': '.$filter['options'][$value];
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Normalise an amount to a 2-dp decimal string.
     */
    protected function money(mixed $amount): string
    {
        return Money::of((string) ($amount ?? '0') ?: '0')->toDecimal();
    }

    /**
     * Sum a key of the given rows as money.
     *
     * @param  iterable<array<string, mixed>>  $rows
     */
    protected function sum(iterable $rows, string $key): string
    {
        $total = Money::zero();

        foreach ($rows as $row) {
            $total = $total->plus((string) ($row[$key] ?? '0') ?: '0');
        }

        return $total->toDecimal();
    }

    /**
     * Percentage of part in whole, to one decimal place.
     */
    protected function percentage(string|int $part, string|int $whole): string
    {
        if (bccomp((string) $whole, '0', 2) === 0) {
            return '0.0';
        }

        return number_format((float) bcdiv(bcmul((string) $part, '100', 6), (string) $whole, 6), 1, '.', '');
    }

    /**
     * Bucket dated amounts by day (ranges up to 62 days) or by month, for trend charts.
     *
     * @param  iterable<array{date: string, amount: string}>  $entries
     * @return array{labels: list<string>, data: list<float>}
     */
    protected function trend(ReportFilters $filters, iterable $entries): array
    {
        $daily = $filters->from->diffInDays($filters->to) <= 62;
        $buckets = [];

        if ($daily) {
            foreach (CarbonPeriod::create($filters->from, $filters->to) as $day) {
                $buckets[$day->format('Y-m-d')] = ['label' => $day->format('d M'), 'total' => Money::zero()];
            }
        } else {
            foreach (CarbonPeriod::create($filters->from->startOfMonth(), '1 month', $filters->to) as $month) {
                $buckets[$month->format('Y-m')] = ['label' => $month->format('M Y'), 'total' => Money::zero()];
            }
        }

        foreach ($entries as $entry) {
            $key = $daily ? substr($entry['date'], 0, 10) : substr($entry['date'], 0, 7);

            if (isset($buckets[$key])) {
                $buckets[$key]['total'] = $buckets[$key]['total']->plus($entry['amount']);
            }
        }

        return [
            'labels' => array_values(array_map(fn (array $bucket): string => $bucket['label'], $buckets)),
            'data' => array_values(array_map(fn (array $bucket): float => (float) $bucket['total']->toDecimal(), $buckets)),
        ];
    }

    /**
     * A bar/doughnut chart of the largest values of a keyed collection.
     *
     * @param  Collection<int|string, array{label: string, value: string|int}>  $items
     * @return array{type: string, labels: list<string>, datasets: list<array{label: string, data: list<float>}>, money: bool}
     */
    protected function rankedChart(Collection $items, string $datasetLabel, string $type = 'bar', bool $money = true, int $limit = 10): array
    {
        $top = $items->sortByDesc(fn (array $item): float => (float) $item['value'])->take($limit)->values();

        return [
            'type' => $type,
            'labels' => $top->pluck('label')->map(fn (mixed $label): string => (string) $label)->all(),
            'datasets' => [['label' => $datasetLabel, 'data' => $top->map(fn (array $item): float => (float) $item['value'])->all()]],
            'money' => $money,
        ];
    }

    /**
     * Summary entry helper.
     *
     * @return array{label: string, value: mixed, type: string}
     */
    protected function stat(string $label, mixed $value, string $type = ReportColumn::MONEY): array
    {
        return ['label' => $label, 'value' => $value, 'type' => $type];
    }
}
