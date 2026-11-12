<?php

namespace App\Models\Concerns;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Approval steps of a document that implements App\Models\Contracts\Approvable. Each submission starts a new
 * round; earlier rounds stay as history.
 */
trait HasApprovals
{
    /**
     * @return MorphMany<Approval, $this>
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('round')->orderBy('sequence');
    }

    /**
     * The requester never decides their own document. Documents with more people involved override this.
     *
     * @return list<int>
     */
    public function approvalExcludedUserIds(): array
    {
        return array_values(array_filter([$this->approvalRequesterId()]));
    }

    public function currentApprovalRound(): int
    {
        return (int) ($this->approvals->max('round') ?? 0);
    }

    /**
     * Steps of the latest round, in order.
     *
     * @return Collection<int, Approval>
     */
    public function currentApprovals(): Collection
    {
        $round = $this->currentApprovalRound();

        return $this->approvals->where('round', $round)->values();
    }

    /**
     * The step waiting for a decision, if any.
     */
    public function pendingApproval(): ?Approval
    {
        return $this->currentApprovals()->first(fn (Approval $approval): bool => $approval->status === ApprovalStatus::Pending);
    }
}
