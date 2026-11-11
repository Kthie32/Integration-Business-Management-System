<?php

namespace App\Enums;

/**
 * Documents that receive a sequential number from the NumberingService.
 */
enum DocumentType: string
{
    case Quotation = 'quotation';
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case Payment = 'payment';
    case Expense = 'expense';
    case Requisition = 'requisition';
    case GoodsReceipt = 'goods_receipt';
    case StockAdjustment = 'stock_adjustment';
    case PayrollRun = 'payroll_run';
    case Payslip = 'payslip';
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Employee = 'employee';
    case Item = 'item';

    public function label(): string
    {
        return match ($this) {
            self::Quotation => 'Quotation',
            self::Invoice => 'Invoice',
            self::Receipt => 'Receipt',
            self::Payment => 'Payment',
            self::Expense => 'Expense',
            self::Requisition => 'Requisition',
            self::GoodsReceipt => 'Goods received note',
            self::StockAdjustment => 'Stock adjustment',
            self::PayrollRun => 'Payroll run',
            self::Payslip => 'Payslip',
            self::Customer => 'Customer code',
            self::Supplier => 'Supplier code',
            self::Employee => 'Employee number',
            self::Item => 'Item code',
        };
    }

    public function defaultPrefix(): string
    {
        return match ($this) {
            self::Quotation => 'QT',
            self::Invoice => 'INV',
            self::Receipt => 'RCT',
            self::Payment => 'PMT',
            self::Expense => 'EXP',
            self::Requisition => 'REQ',
            self::GoodsReceipt => 'GRN',
            self::StockAdjustment => 'ADJ',
            self::PayrollRun => 'PAY',
            self::Payslip => 'PSL',
            self::Customer => 'CUS',
            self::Supplier => 'SUP',
            self::Employee => 'EMP',
            self::Item => 'ITM',
        };
    }

    /**
     * Numbering configuration used until the organization changes it in Settings.
     *
     * @return array{prefix: string, padding: int, reset: string}
     */
    public function defaultNumbering(): array
    {
        return [
            'prefix' => $this->defaultPrefix(),
            'padding' => $this->isMasterRecord() ? 4 : 5,
            'reset' => $this->isMasterRecord() ? NumberingReset::Never->value : NumberingReset::Yearly->value,
        ];
    }

    /**
     * Codes for people, organizations and items (customers, suppliers, employees, items) run continuously, e.g. "CUS-0042".
     */
    public function isMasterRecord(): bool
    {
        return in_array($this, [self::Customer, self::Supplier, self::Employee, self::Item], true);
    }
}
