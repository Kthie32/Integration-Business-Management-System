<?php

namespace App\Services\Reports\Definitions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;

/**
 * Invoice register: every issued invoice dated in the period (FR-RPT-01). Drafts are left out; void invoices are
 * listed but not counted in the totals.
 */
class InvoiceRegisterReport extends Report
{
    public function key(): string
    {
        return 'invoice-register';
    }

    public function title(): string
    {
        return 'Invoice register';
    }

    public function description(): string
    {
        return 'All issued invoices in the period with totals, amounts paid and balances; void invoices are shown but not totalled.';
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
        $statuses = InvoiceStatus::options();
        unset($statuses[InvoiceStatus::Draft->value]);

        return ['status' => ['label' => 'Status', 'options' => $statuses]];
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $invoices = Invoice::query()
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->whereBetween('invoice_date', $filters->dates())
            ->when($filters->option('status'), fn ($query, string $status) => $query->where('status', $status))
            ->with('customer:id,name')
            ->orderBy('invoice_date')
            ->orderBy('number')
            ->get();

        $rows = $invoices->map(fn (Invoice $invoice): array => [
            'number' => $invoice->displayNumber(),
            'date' => $invoice->invoice_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'customer' => $invoice->customer?->name,
            'status' => $invoice->status->label(),
            'total' => $this->money($invoice->total),
            'paid' => $this->money($invoice->amount_paid),
            'balance' => $invoice->status === InvoiceStatus::Void ? '0.00' : $this->money($invoice->balance_due),
            'void' => $invoice->status === InvoiceStatus::Void,
        ])->all();

        $counted = array_filter($rows, fn (array $row): bool => ! $row['void']);
        $rows = array_map(function (array $row): array {
            unset($row['void']);

            return $row;
        }, $rows);

        $byStatus = $invoices->groupBy(fn (Invoice $invoice): string => $invoice->status->label())
            ->map(fn ($group, $label): array => ['label' => (string) $label, 'value' => $group->count()]);

        $total = $this->sum($counted, 'total');
        $paid = $this->sum($counted, 'paid');
        $balance = $this->sum($counted, 'balance');

        return new ReportResult(
            columns: [
                ReportColumn::text('number', 'Invoice'),
                ReportColumn::date('date', 'Date'),
                ReportColumn::date('due_date', 'Due'),
                ReportColumn::text('customer', 'Customer'),
                ReportColumn::text('status', 'Status'),
                ReportColumn::money('total', 'Total'),
                ReportColumn::money('paid', 'Paid'),
                ReportColumn::money('balance', 'Balance'),
            ],
            rows: $rows,
            totals: ['customer' => 'Total excluding void ('.count($counted).')', 'total' => $total, 'paid' => $paid, 'balance' => $balance],
            summary: [
                $this->stat('Invoiced', $total),
                $this->stat('Paid', $paid),
                $this->stat('Balance due', $balance),
                $this->stat('Invoices', count($counted), ReportColumn::NUMBER),
            ],
            chart: $this->rankedChart($byStatus, 'Invoices', 'doughnut', money: false),
        );
    }
}
