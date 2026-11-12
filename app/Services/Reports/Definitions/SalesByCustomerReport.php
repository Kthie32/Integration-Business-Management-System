<?php

namespace App\Services\Reports\Definitions;

use App\Enums\InvoiceStatus;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

/**
 * Sales by customer: issued (non-void) invoices dated in the period, totalled per customer.
 */
class SalesByCustomerReport extends Report
{
    public function key(): string
    {
        return 'sales-by-customer';
    }

    public function title(): string
    {
        return 'Sales by customer';
    }

    public function description(): string
    {
        return 'Invoiced sales per customer in the period with amounts paid and still owed; void invoices excluded.';
    }

    public function group(): string
    {
        return self::GROUP_SALES;
    }

    public function permission(): string
    {
        return 'reports.sales';
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $groups = DB::table('invoices')
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->whereNull('invoices.deleted_at')
            ->whereIn('invoices.status', InvoiceStatus::postedValues())
            ->whereBetween('invoices.invoice_date', $filters->dates())
            ->groupBy('customers.id', 'customers.code', 'customers.name')
            ->selectRaw('customers.code, customers.name, COUNT(*) as invoices, SUM(invoices.total) as total, SUM(invoices.amount_paid) as paid, SUM(invoices.balance_due) as balance')
            ->orderByDesc('total')
            ->get();

        $grandTotal = $this->sum($groups->map(fn (object $row): array => ['total' => (string) $row->total]), 'total');

        $rows = $groups->map(fn (object $row): array => [
            'code' => $row->code,
            'customer' => $row->name,
            'invoices' => (int) $row->invoices,
            'total' => $this->money($row->total),
            'paid' => $this->money($row->paid),
            'balance' => $this->money($row->balance),
            'share' => $this->percentage($this->money($row->total), $grandTotal),
        ])->all();

        return new ReportResult(
            columns: [
                ReportColumn::text('code', 'Code'),
                ReportColumn::text('customer', 'Customer'),
                ReportColumn::number('invoices', 'Invoices'),
                ReportColumn::money('total', 'Invoiced'),
                ReportColumn::money('paid', 'Paid'),
                ReportColumn::money('balance', 'Balance'),
                ReportColumn::percent('share', 'Share'),
            ],
            rows: $rows,
            totals: [
                'customer' => 'Total ('.count($rows).' customers)',
                'invoices' => array_sum(array_column($rows, 'invoices')),
                'total' => $grandTotal,
                'paid' => $this->sum($rows, 'paid'),
                'balance' => $this->sum($rows, 'balance'),
            ],
            summary: [
                $this->stat('Invoiced', $grandTotal),
                $this->stat('Customers', count($rows), ReportColumn::NUMBER),
                $this->stat('Top customer', $rows[0]['customer'] ?? '—', ReportColumn::TEXT),
            ],
            chart: $this->rankedChart(collect($rows)->map(fn (array $row): array => ['label' => (string) $row['customer'], 'value' => $row['total']]), 'Invoiced'),
        );
    }
}
