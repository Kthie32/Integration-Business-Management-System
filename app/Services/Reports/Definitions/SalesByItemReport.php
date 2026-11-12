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
 * Sales by item: invoice lines of issued (non-void) invoices dated in the period, grouped by catalogue item.
 * Free-text lines are grouped by their description.
 */
class SalesByItemReport extends Report
{
    public function key(): string
    {
        return 'sales-by-item';
    }

    public function title(): string
    {
        return 'Sales by item';
    }

    public function description(): string
    {
        return 'Quantities and values sold per item (or free-text description) on issued invoices in the period.';
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
        $groups = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('items', 'items.id', '=', 'invoice_items.item_id')
            ->whereNull('invoices.deleted_at')
            ->whereIn('invoices.status', InvoiceStatus::postedValues())
            ->whereBetween('invoices.invoice_date', $filters->dates())
            ->groupBy('invoice_items.item_id', 'items.code', DB::raw('COALESCE(items.name, invoice_items.description)'))
            ->selectRaw('items.code, COALESCE(items.name, invoice_items.description) as name, SUM(invoice_items.quantity) as quantity, SUM(invoice_items.taxable_amount) as net, SUM(invoice_items.tax_amount) as tax, SUM(invoice_items.line_total) as total')
            ->orderByDesc('total')
            ->get();

        $grandTotal = $this->sum($groups->map(fn (object $row): array => ['total' => (string) $row->total]), 'total');

        $rows = $groups->map(fn (object $row): array => [
            'code' => $row->code ?? '—',
            'item' => $row->name,
            'quantity' => (string) $row->quantity,
            'net' => $this->money($row->net),
            'tax' => $this->money($row->tax),
            'total' => $this->money($row->total),
            'share' => $this->percentage($this->money($row->total), $grandTotal),
        ])->all();

        return new ReportResult(
            columns: [
                ReportColumn::text('code', 'Code'),
                ReportColumn::text('item', 'Item'),
                ReportColumn::quantity('quantity', 'Quantity'),
                ReportColumn::money('net', 'Net sales'),
                ReportColumn::money('tax', 'Tax'),
                ReportColumn::money('total', 'Total'),
                ReportColumn::percent('share', 'Share'),
            ],
            rows: $rows,
            totals: ['item' => 'Total ('.count($rows).' items)', 'net' => $this->sum($rows, 'net'), 'tax' => $this->sum($rows, 'tax'), 'total' => $grandTotal],
            summary: [
                $this->stat('Sales incl. tax', $grandTotal),
                $this->stat('Net sales', $this->sum($rows, 'net')),
                $this->stat('Items sold', count($rows), ReportColumn::NUMBER),
            ],
            chart: $this->rankedChart(collect($rows)->map(fn (array $row): array => ['label' => (string) $row['item'], 'value' => $row['total']]), 'Sales'),
        );
    }
}
