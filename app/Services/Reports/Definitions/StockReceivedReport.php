<?php

namespace App\Services\Reports\Definitions;

use App\Enums\StockMovementType;

/**
 * Stock received through posted goods received notes in the period (FR-RPT-03).
 */
class StockReceivedReport extends StockMovementReport
{
    public function key(): string
    {
        return 'stock-received';
    }

    public function title(): string
    {
        return 'Stock received';
    }

    public function description(): string
    {
        return 'Goods received into stock in the period, per GRN line, with quantities and cost.';
    }

    protected function types(): array
    {
        return [StockMovementType::GoodsReceived];
    }
}
