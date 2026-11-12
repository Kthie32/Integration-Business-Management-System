<?php

namespace App\Services\Reports\Definitions;

use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Illuminate\Support\Collection;

/**
 * Requisitions submitted in the period: pending, approved, rejected, fulfilled, by department (FR-RPT-05).
 * Only requisitions the user may see are included (department heads see their own departments).
 */
class RequisitionsReport extends Report
{
    /**
     * Report status group => statuses.
     */
    public const STATUS_GROUPS = [
        'pending' => [RequisitionStatus::Submitted, RequisitionStatus::InApproval],
        'returned' => [RequisitionStatus::Returned],
        'approved' => [RequisitionStatus::Approved],
        'rejected' => [RequisitionStatus::Rejected],
        'fulfilled' => [RequisitionStatus::PartiallyFulfilled, RequisitionStatus::Fulfilled],
        'cancelled' => [RequisitionStatus::Cancelled],
    ];

    public const STATUS_LABELS = [
        'pending' => 'Pending approval',
        'returned' => 'Returned for revision',
        'approved' => 'Approved, awaiting fulfilment',
        'rejected' => 'Rejected',
        'fulfilled' => 'Fulfilled (fully or partly)',
        'cancelled' => 'Cancelled',
    ];

    public function key(): string
    {
        return 'requisitions';
    }

    public function title(): string
    {
        return 'Requisitions';
    }

    public function description(): string
    {
        return 'Requisitions submitted in the period by status and department, as a list or totalled per department.';
    }

    public function group(): string
    {
        return self::GROUP_REQUISITIONS;
    }

    public function permission(): string
    {
        return 'reports.requisitions';
    }

    public function supportsDepartment(): bool
    {
        return true;
    }

    public function selectFilters(): array
    {
        return [
            'status' => ['label' => 'Status', 'options' => self::STATUS_LABELS],
            'group' => ['label' => 'Show', 'options' => ['list' => 'List of requisitions', 'department' => 'Totals by department']],
        ];
    }

    public static function statusGroupOf(RequisitionStatus $status): ?string
    {
        foreach (self::STATUS_GROUPS as $group => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $group;
            }
        }

        return null;
    }

    public function build(ReportFilters $filters, User $user): ReportResult
    {
        $status = $filters->option('status');

        $requisitions = Requisition::query()
            ->visibleTo($user)
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', $filters->datetimes())
            ->when($filters->departmentId, fn ($query, int $id) => $query->where('department_id', $id))
            ->when($status, fn ($query, string $group) => $query->whereIn('status', array_map(fn (RequisitionStatus $value): string => $value->value, self::STATUS_GROUPS[$group])))
            ->with(['department:id,name', 'requester:id,name'])
            ->orderBy('submitted_at')
            ->get();

        $counts = fn (Collection $set, string $group): int => $set->filter(fn (Requisition $requisition): bool => self::statusGroupOf($requisition->status) === $group)->count();

        $summary = [
            $this->stat('Requisitions', $requisitions->count(), ReportColumn::NUMBER),
            $this->stat('Pending', $counts($requisitions, 'pending'), ReportColumn::NUMBER),
            $this->stat('Approved', $counts($requisitions, 'approved'), ReportColumn::NUMBER),
            $this->stat('Rejected', $counts($requisitions, 'rejected'), ReportColumn::NUMBER),
            $this->stat('Fulfilled', $counts($requisitions, 'fulfilled'), ReportColumn::NUMBER),
            $this->stat('Estimated value', $this->sum($requisitions->map(fn (Requisition $requisition): array => ['v' => (string) $requisition->estimated_total]), 'v')),
        ];

        $byDepartment = $requisitions->groupBy(fn (Requisition $requisition): string => (string) $requisition->department?->name);
        $chart = $this->rankedChart($byDepartment->map(fn (Collection $group, string $name): array => ['label' => $name, 'value' => $group->count()]), 'Requisitions', money: false);

        if ($filters->option('group') === 'department') {
            $rows = $byDepartment->sortKeys()->map(function (Collection $group, string $name) use ($counts): array {
                $row = ['department' => $name, 'total' => $group->count()];

                foreach (array_keys(self::STATUS_GROUPS) as $statusGroup) {
                    $row[$statusGroup] = $counts($group, $statusGroup);
                }

                $row['value'] = $this->sum($group->map(fn (Requisition $requisition): array => ['v' => (string) $requisition->estimated_total]), 'v');

                return $row;
            })->values()->all();

            $totals = ['department' => 'Total', 'total' => $requisitions->count()];

            foreach (array_keys(self::STATUS_GROUPS) as $statusGroup) {
                $totals[$statusGroup] = array_sum(array_column($rows, $statusGroup));
            }

            $totals['value'] = $this->sum($rows, 'value');

            return new ReportResult(
                columns: [
                    ReportColumn::text('department', 'Department'),
                    ReportColumn::number('total', 'Total'),
                    ReportColumn::number('pending', 'Pending'),
                    ReportColumn::number('returned', 'Returned'),
                    ReportColumn::number('approved', 'Approved'),
                    ReportColumn::number('rejected', 'Rejected'),
                    ReportColumn::number('fulfilled', 'Fulfilled'),
                    ReportColumn::number('cancelled', 'Cancelled'),
                    ReportColumn::money('value', 'Estimated value'),
                ],
                rows: $rows,
                totals: $totals,
                summary: $summary,
                chart: $chart,
            );
        }

        $rows = $requisitions->map(fn (Requisition $requisition): array => [
            'number' => $requisition->displayNumber(),
            'date' => $requisition->submitted_at->toDateString(),
            'department' => $requisition->department?->name,
            'requester' => $requisition->requester?->name,
            'priority' => $requisition->priority->label(),
            'purpose' => mb_strimwidth($requisition->purpose, 0, 80, '…'),
            'status' => $requisition->status->label(),
            'value' => $this->money($requisition->estimated_total),
        ])->all();

        return new ReportResult(
            columns: [
                ReportColumn::text('number', 'Requisition'),
                ReportColumn::date('date', 'Submitted'),
                ReportColumn::text('department', 'Department'),
                ReportColumn::text('requester', 'Requested by'),
                ReportColumn::text('priority', 'Priority'),
                ReportColumn::text('purpose', 'Purpose', false),
                ReportColumn::text('status', 'Status'),
                ReportColumn::money('value', 'Estimated value'),
            ],
            rows: $rows,
            totals: ['requester' => 'Total ('.count($rows).')', 'value' => $this->sum($rows, 'value')],
            summary: $summary,
            chart: $chart,
        );
    }
}
