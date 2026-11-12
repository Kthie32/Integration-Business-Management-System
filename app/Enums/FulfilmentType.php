<?php

namespace App\Enums;

/**
 * How an approved requisition line was delivered (FR-REQ-06): issued from stock, or bought and recorded as
 * an expense.
 */
enum FulfilmentType: string
{
    case StockIssue = 'stock_issue';
    case Purchase = 'purchase';

    public function label(): string
    {
        return match ($this) {
            self::StockIssue => 'Issued from stock',
            self::Purchase => 'Purchased',
        };
    }
}
