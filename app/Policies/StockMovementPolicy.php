<?php

namespace App\Policies;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('stock.view');
    }

    /**
     * Only movements recorded directly (issues, transfers, opening balances) are reversed here.
     * Movements created by a document are reversed through that document (e.g. voiding a GRN).
     * The person who recorded a movement cannot reverse it.
     */
    public function reverse(User $actor, StockMovement $movement): bool
    {
        return $actor->can('stock.adjust_approve')
            && $movement->created_by !== $actor->id
            && in_array($movement->type, [StockMovementType::Issue, StockMovementType::TransferOut, StockMovementType::OpeningBalance], true)
            && ! $movement->isReversal();
    }
}
