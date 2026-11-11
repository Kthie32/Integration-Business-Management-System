<?php

namespace App\Enums;

use App\Enums\Contracts\StatusEnum;

/**
 * Invoice lifecycle. A draft is issued (receiving its number and, for stock items, taking stock out of the
 * chosen warehouse); payments move it to partially paid or paid, and reversing a payment moves it back.
 * An issued invoice without payments can be voided, which returns its stock. "Overdue" is not a stored
 * status: it is derived from the due date and the balance (see Invoice::isOverdue()).
 */
enum InvoiceStatus: string implements StatusEnum
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';

    public function canTransitionTo(StatusEnum $status): bool
    {
        $allowed = match ($this) {
            self::Draft => [self::Issued],
            self::Issued => [self::PartiallyPaid, self::Paid, self::Void],
            self::PartiallyPaid => [self::Issued, self::Paid],
            self::Paid => [self::Issued, self::PartiallyPaid],
            self::Void => [],
        };

        return in_array($status, $allowed, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Issued => 'info',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Void => 'danger',
        };
    }

    /**
     * Statuses in which the invoice is owed money and can receive payments.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid], true);
    }

    /**
     * Statuses of invoices that count towards sales and receivables (issued, not void).
     *
     * @return list<string>
     */
    public static function postedValues(): array
    {
        return [self::Issued->value, self::PartiallyPaid->value, self::Paid->value];
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Issued->value, self::PartiallyPaid->value];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
