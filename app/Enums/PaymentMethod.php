<?php

namespace App\Enums;

/**
 * How a customer paid. Every method except cash needs a transaction reference.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case MobileMoney = 'mobile_money';
    case Cheque = 'cheque';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank transfer',
            self::MobileMoney => 'Mobile money',
            self::Cheque => 'Cheque',
            self::Card => 'Card',
        };
    }

    public function requiresReference(): bool
    {
        return $this !== self::Cash;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $method): array => [$method->value => $method->label()])->all();
    }
}
