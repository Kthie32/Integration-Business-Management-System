<?php

namespace App\Enums;

/**
 * The reason a stock movement was recorded. Each type always moves stock in one direction.
 */
enum StockMovementType: string
{
    case OpeningBalance = 'opening_balance';
    case GoodsReceived = 'goods_received';
    case Issue = 'issue';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case SaleIssue = 'sale_issue';
    case Return = 'return';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'Opening balance',
            self::GoodsReceived => 'Goods received',
            self::Issue => 'Issue',
            self::TransferOut => 'Transfer out',
            self::TransferIn => 'Transfer in',
            self::AdjustmentIn => 'Adjustment (+)',
            self::AdjustmentOut => 'Adjustment (−)',
            self::SaleIssue => 'Sale',
            self::Return => 'Return',
            self::Reversal => 'Reversal',
        };
    }

    /**
     * +1 for movements that add stock, -1 for movements that remove it. Reversals take the opposite
     * direction of the movement they reverse, so they have no fixed direction.
     */
    public function direction(): ?int
    {
        return match ($this) {
            self::OpeningBalance, self::GoodsReceived, self::TransferIn, self::AdjustmentIn, self::Return => 1,
            self::Issue, self::TransferOut, self::AdjustmentOut, self::SaleIssue => -1,
            self::Reversal => null,
        };
    }

    public function color(): string
    {
        return match ($this->direction()) {
            1 => 'success',
            -1 => 'danger',
            default => 'secondary',
        };
    }
}
