<?php

namespace App\Services\Reports\Definitions;

use App\Models\Item;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;

/**
 * Active stock items at or below their minimum level, as at now (FR-RPT-03, FR-INV-09).
 */
class LowStockReport extends Report
{
    public function key(): string
    {
        return 'low-stock';
    }

    public function title(): string
    {
        return 'Low stock';
    }

    public function description(): string
    {
        return 'Active stock items whose total quantity is at or below the minimum level, with the shortfall and reorder quantity.';
    }

    public function group(): string
    {
        return self::GROUP_INVENTORY;
    }

    public function permission(): string
    {
        return 'reports.inventory';
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $items = Item::query()->active()->lowStock()->withStockOnHand()->with('category:id,name')->orderBy('items.name')->get();

        $rows = $items->map(fn (Item $item): array => [
            'code' => $item->code,
            'item' => $item->name,
            'category' => $item->category?->name,
            'unit' => $item->unit,
            'minimum' => (string) $item->min_stock_level,
            'on_hand' => (string) $item->getAttribute('stock_on_hand'),
            'shortfall' => bcsub((string) $item->min_stock_level, (string) $item->getAttribute('stock_on_hand'), 3),
            'reorder' => $item->reorder_qty === null ? null : (string) $item->reorder_qty,
        ])->all();

        $outOfStock = count(array_filter($rows, fn (array $row): bool => bccomp($row['on_hand'], '0', 3) <= 0));

        return new ReportResult(
            columns: [
                ReportColumn::text('code', 'Code'),
                ReportColumn::text('item', 'Item'),
                ReportColumn::text('category', 'Category'),
                ReportColumn::text('unit', 'Unit', false),
                ReportColumn::quantity('minimum', 'Minimum'),
                ReportColumn::quantity('on_hand', 'On hand'),
                ReportColumn::quantity('shortfall', 'Shortfall'),
                ReportColumn::quantity('reorder', 'Reorder qty'),
            ],
            rows: $rows,
            summary: [
                $this->stat('Items low on stock', count($rows), ReportColumn::NUMBER),
                $this->stat('Out of stock', $outOfStock, ReportColumn::NUMBER),
            ],
        );
    }
}
