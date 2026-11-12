<?php

namespace App\Services\Reports\Definitions;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;

/**
 * Payments received in the period grouped by payment method (FR-RPT-01).
 */
class PaymentsByMethodReport extends Report
{
    public function key(): string
    {
        return 'payments-by-method';
    }

    public function title(): string
    {
        return 'Payments by method';
    }

    public function description(): string
    {
        return 'Number and value of payments received per method (cash, bank, mobile money…) and each method\'s share.';
    }

    public function group(): string
    {
        return self::GROUP_FINANCIAL;
    }

    public function permission(): string
    {
        return 'reports.financial';
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $groups = Payment::query()
            ->completed()
            ->whereBetween('payment_date', $filters->dates())
            ->selectRaw('method, COUNT(*) as payments, SUM(amount) as total')
            ->groupBy('method')
            ->get()
            ->keyBy(fn (Payment $row): string => $row->method->value);

        $grandTotal = $this->sum($groups->map(fn (Payment $row): array => ['total' => (string) $row->getAttribute('total')]), 'total');

        $rows = [];

        foreach (PaymentMethod::cases() as $method) {
            $row = $groups->get($method->value);

            if ($row === null) {
                continue;
            }

            $amount = $this->money($row->getAttribute('total'));
            $rows[] = [
                'method' => $method->label(),
                'payments' => (int) $row->getAttribute('payments'),
                'amount' => $amount,
                'share' => $this->percentage($amount, $grandTotal),
            ];
        }

        $count = array_sum(array_column($rows, 'payments'));

        return new ReportResult(
            columns: [
                ReportColumn::text('method', 'Method'),
                ReportColumn::number('payments', 'Payments'),
                ReportColumn::money('amount', 'Amount'),
                ReportColumn::percent('share', 'Share'),
            ],
            rows: $rows,
            totals: ['method' => 'Total', 'payments' => $count, 'amount' => $grandTotal, 'share' => $rows === [] ? '0.0' : '100.0'],
            summary: [
                $this->stat('Total collected', $grandTotal),
                $this->stat('Payments', $count, ReportColumn::NUMBER),
                $this->stat('Methods used', count($rows), ReportColumn::NUMBER),
            ],
            chart: [
                'type' => 'doughnut',
                'labels' => array_column($rows, 'method'),
                'datasets' => [['label' => 'Amount', 'data' => array_map(fn (array $row): float => (float) $row['amount'], $rows)]],
                'money' => true,
            ],
        );
    }
}
