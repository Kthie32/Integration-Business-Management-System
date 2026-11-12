<?php

namespace App\Services\Approval;

use App\Enums\QuotationStatus;
use App\Enums\StockAdjustmentStatus;
use App\Models\Approval;
use App\Models\PayrollRun;
use App\Models\Quotation;
use App\Models\StockAdjustment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * "My approvals" (FR-APR-02): everything waiting for the user's decision — current approval-engine steps
 * (requisitions and payroll runs) plus quotations and stock adjustments, which keep their own single-step approval rules.
 */
class ApprovalInbox
{
    public function __construct(private ApprovalService $approvals) {}

    /**
     * @return Collection<int, array{type: string, number: string, title: string, step: string|null, amount: string|null, requester: string|null, since: CarbonInterface|null, url: string}>
     */
    public function itemsFor(User $user): Collection
    {
        $items = $this->approvals->pendingFor($user)->map(function (Approval $step): array {
            $document = $step->approvable;

            return match (true) {
                $document instanceof PayrollRun => [
                    'type' => 'Payroll run',
                    'number' => $document->displayNumber(),
                    'title' => $document->periodLabel().' · '.$document->employee_count.' '.str('employee')->plural($document->employee_count),
                    'step' => $step->name,
                    'amount' => (string) $document->total_gross,
                    'requester' => $document->submitter?->name,
                    'since' => $step->created_at,
                    'url' => $document->approvalUrl(),
                ],
                default => [
                    'type' => 'Requisition',
                    'number' => $document->displayNumber(),
                    'title' => $document->purpose,
                    'step' => $step->name,
                    'amount' => (string) $document->estimated_total,
                    'requester' => $document->requester?->name,
                    'since' => $step->created_at,
                    'url' => $document->approvalUrl(),
                ],
            };
        });

        if ($user->can('quotations.approve')) {
            $items = $items->concat(Quotation::query()
                ->where('status', QuotationStatus::PendingApproval->value)
                ->where('created_by', '!=', $user->id)
                ->with('customer', 'creator')
                ->get()
                ->filter(fn (Quotation $quotation): bool => $user->can('approve', $quotation))
                ->map(fn (Quotation $quotation): array => [
                    'type' => 'Quotation',
                    'number' => $quotation->displayNumber(),
                    'title' => $quotation->customer?->name ?? '',
                    'step' => null,
                    'amount' => (string) $quotation->total,
                    'requester' => $quotation->creator?->name,
                    'since' => $quotation->submitted_at ?? $quotation->updated_at,
                    'url' => route('quotations.show', $quotation),
                ]));
        }

        if ($user->can('stock.adjust_approve')) {
            $items = $items->concat(StockAdjustment::query()
                ->where('status', StockAdjustmentStatus::Pending->value)
                ->where('created_by', '!=', $user->id)
                ->with('warehouse', 'creator')
                ->get()
                ->filter(fn (StockAdjustment $adjustment): bool => $user->can('decide', $adjustment))
                ->map(fn (StockAdjustment $adjustment): array => [
                    'type' => 'Stock adjustment',
                    'number' => (string) $adjustment->number,
                    'title' => $adjustment->reason,
                    'step' => null,
                    'amount' => null,
                    'requester' => $adjustment->creator?->name,
                    'since' => $adjustment->created_at,
                    'url' => route('stock-adjustments.show', $adjustment),
                ]));
        }

        return $items->sortBy(fn (array $item): int => $item['since']?->getTimestamp() ?? 0)->values();
    }

    public function countFor(User $user): int
    {
        return $this->itemsFor($user)->count();
    }
}
