<?php

namespace App\Policies;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;

/**
 * Quotation permissions combined with state rules. The person who prepared a quotation can never approve
 * or return it, Super Administrators included.
 */
class QuotationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('quotations.view');
    }

    public function view(User $actor, Quotation $quotation): bool
    {
        return $actor->can('quotations.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('quotations.create');
    }

    public function update(User $actor, Quotation $quotation): bool
    {
        return $actor->can('quotations.update') && $quotation->isDraft();
    }

    /**
     * Only drafts that never received a number (new quotations and unsubmitted revisions) can be deleted.
     */
    public function delete(User $actor, Quotation $quotation): bool
    {
        return $actor->can('quotations.delete') && $quotation->isDraft() && ($quotation->number === null);
    }

    public function submit(User $actor, Quotation $quotation): bool
    {
        return $this->update($actor, $quotation);
    }

    public function approve(User $actor, Quotation $quotation): bool
    {
        return $actor->can('quotations.approve')
            && $quotation->status === QuotationStatus::PendingApproval
            && $quotation->created_by !== $actor->id;
    }

    public function markSent(User $actor, Quotation $quotation): bool
    {
        return $actor->can('quotations.send') && $quotation->status === QuotationStatus::Approved;
    }

    public function recordDecision(User $actor, Quotation $quotation): bool
    {
        return $actor->can('quotations.send')
            && in_array($quotation->status, [QuotationStatus::Approved, QuotationStatus::Sent], true);
    }

    public function cancel(User $actor, Quotation $quotation): bool
    {
        return $actor->can('quotations.cancel') && $quotation->status->isCancellable();
    }

    public function revise(User $actor, Quotation $quotation): bool
    {
        return $actor->can('quotations.create')
            && $quotation->status->isRevisable()
            && ! $quotation->revisionChild()->exists();
    }

    public function convert(User $actor, Quotation $quotation): bool
    {
        return $actor->can('quotations.convert')
            && $actor->can('invoices.create')
            && $quotation->status === QuotationStatus::Accepted
            && ! $quotation->isConverted();
    }
}
