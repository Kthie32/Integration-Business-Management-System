<?php

namespace App\Services\Reports;

/**
 * One column of a report table. The type decides how a cell is shown on screen, in the PDF and in Excel.
 */
final class ReportColumn
{
    public const TEXT = 'text';

    public const MONEY = 'money';

    public const NUMBER = 'number';

    public const QUANTITY = 'quantity';

    public const PERCENT = 'percent';

    public const DATE = 'date';

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = self::TEXT,
        public readonly bool $sortable = true,
    ) {}

    public static function text(string $key, string $label, bool $sortable = true): self
    {
        return new self($key, $label, self::TEXT, $sortable);
    }

    public static function money(string $key, string $label): self
    {
        return new self($key, $label, self::MONEY);
    }

    public static function number(string $key, string $label): self
    {
        return new self($key, $label, self::NUMBER);
    }

    public static function quantity(string $key, string $label): self
    {
        return new self($key, $label, self::QUANTITY);
    }

    public static function percent(string $key, string $label): self
    {
        return new self($key, $label, self::PERCENT);
    }

    public static function date(string $key, string $label): self
    {
        return new self($key, $label, self::DATE);
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, [self::MONEY, self::NUMBER, self::QUANTITY, self::PERCENT], true);
    }
}
