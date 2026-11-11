<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

/**
 * Invoice permissions combined with state rules: drafts are edited, deleted and issued; issued invoices
 * without payments are voided; voided invoices are copied into a corrected draft.
 */
class InvoicePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('invoices.view');
    }

    public function view(User $actor, Invoice $invoice): bool
    {
        return $actor->can('invoices.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('invoices.create');
    }

    public function update(User $actor, Invoice $invoice): bool
    {
        return $actor->can('invoices.update') && $invoice->isDraft();
    }

    public function delete(User $actor, Invoice $invoice): bool
    {
        return $actor->can('invoices.delete') && $invoice->isDraft();
    }

    public function issue(User $actor, Invoice $invoice): bool
    {
        return $actor->can('invoices.issue') && $invoice->isDraft();
    }

    public function void(User $actor, Invoice $invoice): bool
    {
        return $actor->can('invoices.void')
            && $invoice->status === InvoiceStatus::Issued
            && ! $invoice->hasCompletedPayments();
    }

    public function copy(User $actor, Invoice $invoice): bool
    {
        return $actor->can('invoices.create') && $invoice->status === InvoiceStatus::Void;
    }

    public function recordPayment(User $actor, Invoice $invoice): bool
    {
        return $actor->can('payments.record') && $invoice->status->isOpen();
    }
}
