<?php

namespace App\Services\Reports\Definitions;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared base for the stock received and stock issued reports: movements of the given types in the period that
 * have not been reversed, valued at their unit cost.
 */
abstract class StockMovementReport extends Report
{
    /**
     * @return list<StockMovementType>
     */
    abstract protected function types(): array;

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
        return ['warehouse' => ['label' => 'Warehouse', 'options' => StockBalancesReport::warehouseOptions()]];
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $movements = StockMovement::query()
            ->notReversed()
            ->whereIn('type', array_map(fn (StockMovementType $type): string => $type->value, $this->types()))
            ->whereBetween('moved_at', $filters->datetimes())
            ->when($filters->option('warehouse'), fn ($query, string $id) => $query->where('warehouse_id', (int) $id))
            ->with(['item:id,code,name,unit', 'warehouse:id,code', 'reference'])
            ->orderBy('moved_at')
            ->orderBy('id')
            ->get();

        $rows = $movements->map(fn (StockMovement $movement): array => [
            'date' => $movement->moved_at->toDateString(),
            'type' => $movement->type->label(),
            'reference' => $this->referenceLabel($movement->reference),
            'code' => $movement->item->code,
            'item' => $movement->item->name,
            'warehouse' => $movement->warehouse->code,
            'quantity' => (string) $movement->quantity,
            'unit_cost' => (string) $movement->unit_cost,
            'value' => $movement->value(),
        ])->all();

        $total = $this->sum($rows, 'value');
        $trend = $this->trend($filters, array_map(fn (array $row): array => ['date' => $row['date'], 'amount' => $row['value']], $rows));

        return new ReportResult(
            columns: [
                ReportColumn::date('date', 'Date'),
                ReportColumn::text('type', 'Type'),
                ReportColumn::text('reference', 'Reference'),
                ReportColumn::text('code', 'Code'),
                ReportColumn::text('item', 'Item'),
                ReportColumn::text('warehouse', 'Warehouse'),
                ReportColumn::quantity('quantity', 'Quantity'),
                ReportColumn::money('unit_cost', 'Unit cost'),
                ReportColumn::money('value', 'Value'),
            ],
            rows: $rows,
            totals: ['item' => 'Total ('.count($rows).' movements)', 'value' => $total],
            summary: [
                $this->stat('Total value', $total),
                $this->stat('Movements', count($rows), ReportColumn::NUMBER),
                $this->stat('Items', $movements->pluck('item_id')->unique()->count(), ReportColumn::NUMBER),
            ],
            chart: ['type' => 'bar', 'labels' => $trend['labels'], 'datasets' => [['label' => 'Value', 'data' => $trend['data']]], 'money' => true],
        );
    }

    private function referenceLabel(?Model $reference): string
    {
        if ($reference === null) {
            return '—';
        }

        if (method_exists($reference, 'displayNumber')) {
            return (string) $reference->displayNumber();
        }

        return (string) ($reference->getAttribute('number') ?? class_basename($reference).' #'.$reference->getKey());
    }
}
