<?php

namespace App\Services\Reports;

use App\Services\System\SettingsService;
use Carbon\CarbonImmutable;

/**
 * Formats report cells and summary values for the screen, PDF and print views.
 */
class ReportFormatter
{
    public function __construct(private SettingsService $settings) {}

    public function cell(ReportColumn $column, mixed $value): string
    {
        return $this->value($column->type, $value);
    }

    public function value(string $type, mixed $value): string
    {
        if ($value === null || $value === '') {
            return $type === ReportColumn::TEXT || $type === ReportColumn::DATE ? '' : '—';
        }

        return match ($type) {
            ReportColumn::MONEY => $this->settings->formatMoney((string) $value),
            ReportColumn::NUMBER => number_format((float) $value),
            ReportColumn::QUANTITY => $this->quantity((string) $value),
            ReportColumn::PERCENT => $value.'%',
            ReportColumn::DATE => $this->settings->formatDate(CarbonImmutable::parse((string) $value)),
            default => (string) $value,
        };
    }

    public function period(ReportFilters $filters): string
    {
        return $this->settings->formatDate($filters->from).' – '.$this->settings->formatDate($filters->to);
    }

    /**
     * A quantity without trailing zeros, e.g. "12.5" or "1,200".
     */
    private function quantity(string $value): string
    {
        $decimals = str_contains($value, '.') ? strlen(rtrim(substr($value, strpos($value, '.') + 1), '0')) : 0;

        return number_format((float) $value, $decimals);
    }
}
