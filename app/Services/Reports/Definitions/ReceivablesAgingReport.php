<?php

namespace App\Services\Reports\Definitions;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Receivables ageing as at today (FR-RPT-01): open balances per customer by days past the due date.
 */
class ReceivablesAgingReport extends Report
{
    /**
     * Bucket key => label.
     */
    public const BUCKETS = [
        'current' => 'Current',
        'days_1_30' => '1–30 days',
        'days_31_60' => '31–60 days',
        'days_61_90' => '61–90 days',
        'days_over_90' => '90+ days',
    ];

    public function key(): string
    {
        return 'receivables-aging';
    }

    public function title(): string
    {
        return 'Receivables ageing';
    }

    public function description(): string
    {
        return 'Outstanding invoice balances per customer as at today: not yet due, 1–30, 31–60, 61–90 and over 90 days overdue.';
    }

    public function group(): string
    {
        return self::GROUP_FINANCIAL;
    }

    public function permission(): string
    {
        return 'reports.financial';
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    public static function bucketFor(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => 'days_1_30',
            $daysOverdue <= 60 => 'days_31_60',
            $daysOverdue <= 90 => 'days_61_90',
            default => 'days_over_90',
        };
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $today = CarbonImmutable::today();
        $invoices = Invoice::query()->open()->where('balance_due', '>', 0)->with('customer:id,name')->get();
        $customers = [];

        foreach ($invoices as $invoice) {
            $id = $invoice->customer_id;
            $customers[$id] ??= ['customer' => (string) $invoice->customer?->name, 'invoices' => 0]
                + array_fill_keys(array_keys(self::BUCKETS), Money::zero()) + ['total' => Money::zero()];

            $days = (int) $invoice->due_date->startOfDay()->diffInDays($today, false);
            $bucket = self::bucketFor($days);
            $customers[$id][$bucket] = $customers[$id][$bucket]->plus((string) $invoice->balance_due);
            $customers[$id]['total'] = $customers[$id]['total']->plus((string) $invoice->balance_due);
            $customers[$id]['invoices']++;
        }

        $rows = array_values(array_map(fn (array $customer): array => array_map(
            fn (mixed $value): mixed => $value instanceof Money ? $value->toDecimal() : $value, $customer), $customers));

        usort($rows, fn (array $a, array $b): int => bccomp($b['total'], $a['total'], 2));

        $totals = ['customer' => 'Total ('.count($rows).' customers)', 'invoices' => array_sum(array_column($rows, 'invoices'))];

        foreach ([...array_keys(self::BUCKETS), 'total'] as $key) {
            $totals[$key] = $this->sum($rows, $key);
        }

        $overdue = bcsub($totals['total'], $totals['current'], 2);

        return new ReportResult(
            columns: [
                ReportColumn::text('customer', 'Customer'),
                ReportColumn::number('invoices', 'Invoices'),
                ...array_map(fn (string $key, string $label): ReportColumn => ReportColumn::money($key, $label), array_keys(self::BUCKETS), self::BUCKETS),
                ReportColumn::money('total', 'Total due'),
            ],
            rows: $rows,
            totals: $totals,
            summary: [
                $this->stat('Outstanding', $totals['total']),
                $this->stat('Overdue', $overdue),
                $this->stat('Over 90 days', $totals['days_over_90']),
                $this->stat('Customers owing', count($rows), ReportColumn::NUMBER),
            ],
            chart: [
                'type' => 'bar',
                'labels' => array_values(self::BUCKETS),
                'datasets' => [['label' => 'Outstanding', 'data' => array_map(fn (string $key): float => (float) $totals[$key], array_keys(self::BUCKETS))]],
                'money' => true,
            ],
        );
    }
}
