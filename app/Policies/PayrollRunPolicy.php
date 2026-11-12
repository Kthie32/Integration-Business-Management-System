<?php

namespace App\Policies;

use App\Enums\PayrollRunStatus;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Approval\ApprovalService;

/**
 * Payroll runs are restricted to payroll.* permissions (FR-PRL-09). Preparers create, calculate and submit;
 * approvers decide through the approval engine, which never lets whoever created, calculated or submitted the
 * run decide it; Finance marks approved runs paid. Approved and paid runs are read-only.
 */
class PayrollRunPolicy
{
    public function __construct(private ApprovalService $approvals) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('payroll.view') || $actor->can('payroll.prepare');
    }

    public function view(User $actor, PayrollRun $run): bool
    {
        return $this->viewAny($actor)
            || $this->approvals->canAct($actor, $run)
            || $this->approvals->hasActedOn($actor, $run);
    }

    public function create(User $actor): bool
    {
        return $actor->can('payroll.prepare');
    }

    /**
     * Calculate, recalculate and change adjustments.
     */
    public function prepare(User $actor, PayrollRun $run): bool
    {
        return $actor->can('payroll.prepare') && $run->status->isOpen();
    }

    public function submit(User $actor, PayrollRun $run): bool
    {
        return $actor->can('payroll.prepare') && $run->status === PayrollRunStatus::Calculated;
    }

    public function decide(User $actor, PayrollRun $run): bool
    {
        return $run->status === PayrollRunStatus::PendingApproval && $this->approvals->canAct($actor, $run);
    }

    public function markPaid(User $actor, PayrollRun $run): bool
    {
        return $actor->can('payroll.mark_paid') && $run->status === PayrollRunStatus::Approved;
    }

    public function delete(User $actor, PayrollRun $run): bool
    {
        return $actor->can('payroll.prepare') && $run->status->isOpen() && $run->number === null;
    }

    /**
     * Register, deductions schedule and payment list (FR-PRL-08).
     */
    public function export(User $actor, PayrollRun $run): bool
    {
        return $actor->can('payroll.view') && $run->status !== PayrollRunStatus::Draft;
    }
}
