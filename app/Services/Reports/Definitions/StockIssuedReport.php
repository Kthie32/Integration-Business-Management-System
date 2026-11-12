<?php

namespace App\Services\Reports\Definitions;

use App\Enums\StockMovementType;

/**
 * Stock issued in the period: direct and requisition issues and sales on issued invoices (FR-RPT-03).
 */
class StockIssuedReport extends StockMovementReport
{
    public function key(): string
    {
        return 'stock-issued';
    }

    public function title(): string
    {
        return 'Stock issued';
    }

    public function description(): string
    {
        return 'Stock that left the warehouses in the period through issues, requisitions and invoiced sales, at cost.';
    }

    protected function types(): array
    {
        return [StockMovementType::Issue, StockMovementType::SaleIssue];
    }
}
