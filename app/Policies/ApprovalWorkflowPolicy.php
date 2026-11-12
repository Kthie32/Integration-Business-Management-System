<?php

namespace App\Policies;

use App\Models\ApprovalWorkflow;
use App\Models\User;

/**
 * Approval workflows are configuration managed with `approvals.configure`.
 */
class ApprovalWorkflowPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('approvals.configure');
    }

    public function create(User $actor): bool
    {
        return $actor->can('approvals.configure');
    }

    public function update(User $actor, ApprovalWorkflow $workflow): bool
    {
        return $actor->can('approvals.configure');
    }

    public function delete(User $actor, ApprovalWorkflow $workflow): bool
    {
        return $actor->can('approvals.configure');
    }
}
