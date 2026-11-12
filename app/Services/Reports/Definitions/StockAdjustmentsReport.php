<?php

namespace App\Services\Reports\Definitions;

use App\Enums\StockAdjustmentStatus;
use App\Models\StockAdjustmentItem;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;

/**
 * Stock adjustment lines dated in the period with their approval status (FR-RPT-03).
 */
class StockAdjustmentsReport extends Report
{
    public function key(): string
    {
        return 'stock-adjustments';
    }

    public function title(): string
    {
        return 'Stock adjustments';
    }

    public function description(): string
    {
        return 'Every adjustment line in the period with the quantity change, value, reason and approval status.';
    }

    public function group(): string
    {
        return self::GROUP_INVENTORY;
    }

    public function permission(): string
    {
        return 'reports.inventory';
    }

    public function selectFilters(): array
    {
        return [
            'status' => ['label' => 'Status', 'options' => collect(StockAdjustmentStatus::cases())
                ->mapWithKeys(fn (StockAdjustmentStatus $status): array => [$status->value => $status->label()])->all()],
            'warehouse' => ['label' => 'Warehouse', 'options' => StockBalancesReport::warehouseOptions()],
        ];
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $lines = StockAdjustmentItem::query()
            ->whereHas('stockAdjustment', fn ($query) => $query
                ->whereBetween('adjusted_at', $filters->dates())
                ->when($filters->option('status'), fn ($inner, string $status) => $inner->where('status', $status))
                ->when($filters->option('warehouse'), fn ($inner, string $id) => $inner->where('warehouse_id', (int) $id)))
            ->with(['stockAdjustment:id,number,adjusted_at,warehouse_id,status,reason', 'stockAdjustment.warehouse:id,code', 'item:id,code,name'])
            ->get()
            ->sortBy(fn (StockAdjustmentItem $line): string => $line->stockAdjustment->adjusted_at->toDateString().'|'.$line->stockAdjustment->number)
            ->values();

        $rows = $lines->map(fn (StockAdjustmentItem $line): array => [
            'number' => $line->stockAdjustment->number,
            'date' => $line->stockAdjustment->adjusted_at->toDateString(),
            'warehouse' => $line->stockAdjustment->warehouse?->code,
            'item' => $line->item->code.' '.$line->item->name,
            'change' => (string) $line->quantity_change,
            'unit_cost' => $line->unit_cost === null ? null : (string) $line->unit_cost,
            'value' => $line->unit_cost === null ? null : bcadd(bcmul((string) $line->quantity_change, (string) $line->unit_cost, 6), '0', 2),
            'status' => $line->stockAdjustment->status->label(),
            'reason' => $line->stockAdjustment->reason,
        ])->all();

        $approved = $lines->filter(fn (StockAdjustmentItem $line): bool => $line->stockAdjustment->status === StockAdjustmentStatus::Approved);
        $approvedRows = array_values(array_intersect_key($rows, $approved->all()));
        $increase = $this->sum(array_filter($approvedRows, fn (array $row): bool => bccomp((string) ($row['value'] ?? '0'), '0', 2) > 0), 'value');
        $decrease = $this->sum(array_filter($approvedRows, fn (array $row): bool => bccomp((string) ($row['value'] ?? '0'), '0', 2) < 0), 'value');

        $byStatus = $lines->groupBy(fn (StockAdjustmentItem $line): string => $line->stockAdjustment->status->label())
            ->map(fn ($group, $label): array => ['label' => (string) $label, 'value' => $group->pluck('stock_adjustment_id')->unique()->count()]);

        return new ReportResult(
            columns: [
                ReportColumn::text('number', 'Adjustment'),
                ReportColumn::date('date', 'Date'),
                ReportColumn::text('warehouse', 'Warehouse'),
                ReportColumn::text('item', 'Item'),
                ReportColumn::quantity('change', 'Change'),
                ReportColumn::money('unit_cost', 'Unit cost'),
                ReportColumn::money('value', 'Value'),
                ReportColumn::text('status', 'Status'),
                ReportColumn::text('reason', 'Reason', false),
            ],
            rows: $rows,
            totals: ['item' => 'Total ('.count($rows).' lines)', 'value' => $this->sum($rows, 'value')],
            summary: [
                $this->stat('Adjustments', $lines->pluck('stock_adjustment_id')->unique()->count(), ReportColumn::NUMBER),
                $this->stat('Approved increases', $increase),
                $this->stat('Approved decreases', $decrease),
            ],
            chart: $this->rankedChart($byStatus, 'Adjustments', 'doughnut', money: false),
        );
    }
}
