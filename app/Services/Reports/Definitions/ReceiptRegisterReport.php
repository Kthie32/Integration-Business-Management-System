<?php

namespace App\Services\Reports\Definitions;

use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;

/**
 * Receipt register: receipts dated in the period (FR-RPT-01). Void receipts are listed but not totalled.
 */
class ReceiptRegisterReport extends Report
{
    public function key(): string
    {
        return 'receipt-register';
    }

    public function title(): string
    {
        return 'Receipt register';
    }

    public function description(): string
    {
        return 'All receipts issued in the period with customer, invoice and method; void receipts are shown but not totalled.';
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
        return ['status' => ['label' => 'Status', 'options' => ReceiptStatus::options()]];
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $receipts = Receipt::query()
            ->whereBetween('receipt_date', $filters->dates())
            ->when($filters->option('status'), fn ($query, string $status) => $query->where('status', $status))
            ->with(['customer:id,name', 'invoice:id,number', 'payment:id,method'])
            ->orderBy('receipt_date')
            ->orderBy('number')
            ->get();

        $rows = $receipts->map(fn (Receipt $receipt): array => [
            'number' => $receipt->number,
            'date' => $receipt->receipt_date->toDateString(),
            'customer' => $receipt->customer?->name,
            'invoice' => $receipt->invoice?->number,
            'method' => $receipt->payment?->method->label(),
            'status' => $receipt->status->label(),
            'amount' => $this->money($receipt->amount),
        ])->all();

        $valid = $receipts->filter(fn (Receipt $receipt): bool => $receipt->status === ReceiptStatus::Valid);
        $total = $this->sum($valid->map(fn (Receipt $receipt): array => ['amount' => (string) $receipt->amount]), 'amount');
        $voidCount = $receipts->count() - $valid->count();
        $trend = $this->trend($filters, $valid->map(fn (Receipt $receipt): array => ['date' => $receipt->receipt_date->toDateString(), 'amount' => (string) $receipt->amount]));

        return new ReportResult(
            columns: [
                ReportColumn::text('number', 'Receipt'),
                ReportColumn::date('date', 'Date'),
                ReportColumn::text('customer', 'Customer'),
                ReportColumn::text('invoice', 'Invoice'),
                ReportColumn::text('method', 'Method'),
                ReportColumn::text('status', 'Status'),
                ReportColumn::money('amount', 'Amount'),
            ],
            rows: $rows,
            totals: ['customer' => 'Total valid ('.$valid->count().')', 'amount' => $total],
            summary: [
                $this->stat('Total receipted', $total),
                $this->stat('Valid receipts', $valid->count(), ReportColumn::NUMBER),
                $this->stat('Void receipts', $voidCount, ReportColumn::NUMBER),
            ],
            chart: ['type' => 'line', 'labels' => $trend['labels'], 'datasets' => [['label' => 'Receipted', 'data' => $trend['data']]], 'money' => true],
        );
    }
}
