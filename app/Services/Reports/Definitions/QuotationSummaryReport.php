<?php

namespace App\Services\Reports\Definitions;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;

/**
 * Quotations generated in the period: accepted, rejected, pending and the conversion rate (FR-RPT-02).
 * "Generated" means numbered (submitted at least once); superseded revisions are left out so a revised quotation
 * counts once. The conversion rate is accepted ÷ generated, cancelled quotations excluded.
 */
class QuotationSummaryReport extends Report
{
    /**
     * @var list<QuotationStatus>
     */
    public const PENDING = [QuotationStatus::PendingApproval, QuotationStatus::Approved, QuotationStatus::Sent];

    public function key(): string
    {
        return 'quotations';
    }

    public function title(): string
    {
        return 'Quotations summary';
    }

    public function description(): string
    {
        return 'Quotations generated in the period with how many were accepted, rejected or are still pending, and the conversion rate.';
    }

    public function group(): string
    {
        return self::GROUP_SALES;
    }

    public function permission(): string
    {
        return 'reports.sales';
    }

    public function selectFilters(): array
    {
        $statuses = QuotationStatus::options();
        unset($statuses[QuotationStatus::Draft->value], $statuses[QuotationStatus::Superseded->value]);

        return ['status' => ['label' => 'Status', 'options' => $statuses]];
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $quotations = Quotation::query()
            ->whereNotNull('number')
            ->whereNotIn('status', [QuotationStatus::Draft->value, QuotationStatus::Superseded->value])
            ->whereBetween('quote_date', $filters->dates())
            ->with('customer:id,name')
            ->orderBy('quote_date')
            ->orderBy('number')
            ->get();

        $count = fn (array $statuses): int => $quotations->filter(fn (Quotation $quotation): bool => in_array($quotation->status, $statuses, true))->count();

        $generated = $quotations->count();
        $cancelled = $count([QuotationStatus::Cancelled]);
        $accepted = $count([QuotationStatus::Accepted]);
        $conversion = $this->percentage($accepted, $generated - $cancelled);

        $listed = $filters->option('status') === null
            ? $quotations
            : $quotations->filter(fn (Quotation $quotation): bool => $quotation->status->value === $filters->option('status'));

        $rows = $listed->values()->map(fn (Quotation $quotation): array => [
            'number' => $quotation->displayNumber(),
            'date' => $quotation->quote_date->toDateString(),
            'valid_until' => $quotation->valid_until->toDateString(),
            'customer' => $quotation->customer?->name,
            'status' => $quotation->status->label(),
            'total' => $this->money($quotation->total),
        ])->all();

        $byStatus = $quotations->groupBy(fn (Quotation $quotation): string => $quotation->status->label())
            ->map(fn ($group, $label): array => ['label' => (string) $label, 'value' => $group->count()]);

        return new ReportResult(
            columns: [
                ReportColumn::text('number', 'Quotation'),
                ReportColumn::date('date', 'Date'),
                ReportColumn::date('valid_until', 'Valid until'),
                ReportColumn::text('customer', 'Customer'),
                ReportColumn::text('status', 'Status'),
                ReportColumn::money('total', 'Total'),
            ],
            rows: $rows,
            totals: ['customer' => 'Total ('.count($rows).')', 'total' => $this->sum($rows, 'total')],
            summary: [
                $this->stat('Generated', $generated, ReportColumn::NUMBER),
                $this->stat('Accepted', $accepted, ReportColumn::NUMBER),
                $this->stat('Rejected', $count([QuotationStatus::Rejected]), ReportColumn::NUMBER),
                $this->stat('Pending', $count(self::PENDING), ReportColumn::NUMBER),
                $this->stat('Expired', $count([QuotationStatus::Expired]), ReportColumn::NUMBER),
                $this->stat('Conversion rate', $conversion, ReportColumn::PERCENT),
            ],
            chart: $this->rankedChart($byStatus, 'Quotations', 'doughnut', money: false),
            notes: ['Conversion rate = accepted ÷ quotations generated (cancelled excluded). Revised quotations count once.'],
        );
    }
}
