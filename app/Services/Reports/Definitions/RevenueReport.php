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
 * Revenue: completed payments received in the period (FR-RPT-01). Reversed payments are left out.
 */
class RevenueReport extends Report
{
    public function key(): string
    {
        return 'revenue';
    }

    public function title(): string
    {
        return 'Revenue (payments received)';
    }

    public function description(): string
    {
        return 'Every payment received in the period, with its receipt, customer, invoice and method.';
    }

    public function group(): string
    {
        return self::GROUP_FINANCIAL;
    }

    public function permission(): string
    {
        return 'reports.financial';
    }

    public function selectFilters(): array
    {
        return ['method' => ['label' => 'Method', 'options' => PaymentMethod::options()]];
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $payments = Payment::query()
            ->completed()
            ->whereBetween('payment_date', $filters->dates())
            ->when($filters->option('method'), fn ($query, string $method) => $query->where('method', $method))
            ->with(['customer:id,name', 'invoice:id,number', 'receipt:id,payment_id,number'])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $rows = $payments->map(fn (Payment $payment): array => [
            'date' => $payment->payment_date->toDateString(),
            'receipt' => $payment->receipt?->number ?? $payment->number,
            'customer' => $payment->customer?->name,
            'invoice' => $payment->invoice?->number,
            'method' => $payment->method->label(),
            'reference' => $payment->reference,
            'amount' => $this->money($payment->amount),
        ])->all();

        $total = $this->sum($rows, 'amount');
        $count = count($rows);
        $trend = $this->trend($filters, array_map(fn (array $row): array => ['date' => $row['date'], 'amount' => $row['amount']], $rows));

        return new ReportResult(
            columns: [
                ReportColumn::date('date', 'Date'),
                ReportColumn::text('receipt', 'Receipt'),
                ReportColumn::text('customer', 'Customer'),
                ReportColumn::text('invoice', 'Invoice'),
                ReportColumn::text('method', 'Method'),
                ReportColumn::text('reference', 'Reference'),
                ReportColumn::money('amount', 'Amount'),
            ],
            rows: $rows,
            totals: ['customer' => 'Total ('.$count.')', 'amount' => $total],
            summary: [
                $this->stat('Total collected', $total),
                $this->stat('Payments', $count, ReportColumn::NUMBER),
                $this->stat('Average payment', $count > 0 ? bcdiv($total, (string) $count, 2) : '0.00'),
            ],
            chart: ['type' => 'line', 'labels' => $trend['labels'], 'datasets' => [['label' => 'Collected', 'data' => $trend['data']]], 'money' => true],
        );
    }
}
