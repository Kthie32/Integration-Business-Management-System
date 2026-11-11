<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('stock.adjust') || $actor->can('stock.adjust_approve') || $actor->can('stock.view');
    }

    public function view(User $actor, StockAdjustment $adjustment): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->can('stock.adjust');
    }

    /**
     * Segregation of duties: nobody decides on an adjustment they raised, Super Administrators included.
     */
    public function decide(User $actor, StockAdjustment $adjustment): bool
    {
        return $actor->can('stock.adjust_approve') && $adjustment->isPending() && $adjustment->created_by !== $actor->id;
    }

    public function cancel(User $actor, StockAdjustment $adjustment): bool
    {
        return $adjustment->isPending() && $adjustment->created_by === $actor->id;
    }
}
