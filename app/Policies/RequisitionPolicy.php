<?php

namespace App\Policies;

use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use App\Models\User;
use App\Services\Approval\ApprovalService;

/**
 * Who sees and acts on requisitions (docs/05 §2–3). Requesters see their own, department heads those of the
 * departments they head, and view-all / fulfil holders every requisition. Anyone who can decide the current
 * step, or decided an earlier one, can open it too.
 */
class RequisitionPolicy
{
    public function __construct(private ApprovalService $approvals) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('requisitions.view_own')
            || $actor->can('requisitions.view_department')
            || $actor->can('requisitions.view_all')
            || $actor->can('requisitions.approve')
            || $actor->can('requisitions.fulfil');
    }

    public function view(User $actor, Requisition $requisition): bool
    {
        if ($actor->can('requisitions.view_all') || $actor->can('requisitions.fulfil')) {
            return true;
        }

        if ($requisition->requested_by === $actor->id && $actor->can('requisitions.view_own')) {
            return true;
        }

        if ($actor->can('requisitions.view_department') && $actor->isHeadOf($requisition->department)) {
            return true;
        }

        return $this->approvals->hasActedOn($actor, $requisition) || $this->approvals->canAct($actor, $requisition);
    }

    public function create(User $actor): bool
    {
        return $actor->can('requisitions.create');
    }

    /**
     * Only the requester edits, and only while the requisition is a draft or returned for revision.
     */
    public function update(User $actor, Requisition $requisition): bool
    {
        return $requisition->requested_by === $actor->id && $requisition->isEditable() && $actor->can('requisitions.create');
    }

    public function delete(User $actor, Requisition $requisition): bool
    {
        return $requisition->requested_by === $actor->id
            && $requisition->status === RequisitionStatus::Draft
            && $requisition->number === null;
    }

    public function submit(User $actor, Requisition $requisition): bool
    {
        return $this->update($actor, $requisition);
    }

    /**
     * Segregation of duties is enforced by the approval engine: never the requester, never two steps of
     * the same round, Super Administrators included (FR-REQ-05).
     */
    public function decide(User $actor, Requisition $requisition): bool
    {
        return $requisition->status->isAwaitingApproval() && $this->approvals->canAct($actor, $requisition);
    }

    public function cancel(User $actor, Requisition $requisition): bool
    {
        return $requisition->requested_by === $actor->id
            && ($requisition->status->isAwaitingApproval() || $requisition->status === RequisitionStatus::Returned);
    }

    public function fulfil(User $actor, Requisition $requisition): bool
    {
        return $actor->can('requisitions.fulfil') && $requisition->status->isFulfillable();
    }
}
