<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('stock.view') || $actor->can('stock.receive');
    }

    public function view(User $actor, GoodsReceipt $receipt): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('stock.receive');
    }

    public function update(User $actor, GoodsReceipt $receipt): bool
    {
        return $actor->can('stock.receive') && $receipt->isDraft();
    }

    public function delete(User $actor, GoodsReceipt $receipt): bool
    {
        return $this->update($actor, $receipt);
    }

    public function post(User $actor, GoodsReceipt $receipt): bool
    {
        return $this->update($actor, $receipt);
    }

    /**
     * Voiding takes stock back out, so it is a correction approved by a supervisor rather than the receiver.
     */
    public function void(User $actor, GoodsReceipt $receipt): bool
    {
        return $actor->can('stock.adjust_approve') && $receipt->posted_by !== $actor->id && $receipt->voided_at === null && ! $receipt->isDraft();
    }
}
