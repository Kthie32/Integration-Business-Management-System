<?php

namespace App\Services\Reports\Definitions;

use App\Models\ItemCategory;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;

/**
 * Stock balances with valuation at weighted average cost, as at now (FR-RPT-03).
 */
class StockBalancesReport extends Report
{
    public function key(): string
    {
        return 'stock-balances';
    }

    public function title(): string
    {
        return 'Stock balances & valuation';
    }

    public function description(): string
    {
        return 'Quantity on hand per item and warehouse, valued at weighted average cost.';
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

    public function selectFilters(): array
    {
        return [
            'warehouse' => ['label' => 'Warehouse', 'options' => self::warehouseOptions()],
            'category' => ['label' => 'Category', 'options' => ItemCategory::query()->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn (string $name, int $id): array => [(string) $id => $name])->all()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function warehouseOptions(): array
    {
        return Warehouse::query()->orderBy('name')->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Warehouse $warehouse): array => [(string) $warehouse->id => $warehouse->code.' – '.$warehouse->name])->all();
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $levels = StockLevel::query()
            ->where('quantity', '!=', 0)
            ->when($filters->option('warehouse'), fn ($query, string $id) => $query->where('warehouse_id', (int) $id))
            ->when($filters->option('category'), fn ($query, string $id) => $query->whereHas('item', fn ($items) => $items->where('item_category_id', (int) $id)))
            ->with(['item:id,code,name,unit,item_category_id', 'item.category:id,name', 'warehouse:id,code,name'])
            ->get()
            ->sortBy(fn (StockLevel $level): string => $level->item->name.'|'.$level->warehouse->code)
            ->values();

        $rows = $levels->map(fn (StockLevel $level): array => [
            'code' => $level->item->code,
            'item' => $level->item->name,
            'category' => $level->item->category?->name,
            'warehouse' => $level->warehouse->code,
            'unit' => $level->item->unit,
            'quantity' => (string) $level->quantity,
            'average_cost' => (string) $level->average_cost,
            'value' => $level->value(),
        ])->all();

        $total = $this->sum($rows, 'value');
        $byCategory = collect($rows)->groupBy(fn (array $row): string => $row['category'] ?? 'Uncategorised')
            ->map(fn ($group, $name): array => ['label' => (string) $name, 'value' => $this->sum($group, 'value')]);

        return new ReportResult(
            columns: [
                ReportColumn::text('code', 'Code'),
                ReportColumn::text('item', 'Item'),
                ReportColumn::text('category', 'Category'),
                ReportColumn::text('warehouse', 'Warehouse'),
                ReportColumn::text('unit', 'Unit', false),
                ReportColumn::quantity('quantity', 'On hand'),
                ReportColumn::money('average_cost', 'Average cost'),
                ReportColumn::money('value', 'Value'),
            ],
            rows: $rows,
            totals: ['item' => 'Total ('.count($rows).' balances)', 'value' => $total],
            summary: [
                $this->stat('Stock value', $total),
                $this->stat('Items in stock', $levels->pluck('item_id')->unique()->count(), ReportColumn::NUMBER),
                $this->stat('Warehouses', $levels->pluck('warehouse_id')->unique()->count(), ReportColumn::NUMBER),
            ],
            chart: $this->rankedChart($byCategory, 'Value', 'doughnut'),
        );
    }
}
