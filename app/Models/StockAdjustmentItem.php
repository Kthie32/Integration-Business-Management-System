<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a stock adjustment: a positive change adds stock, a negative change removes it.
 * The unit cost is only used for increases; when empty, the item's current average cost applies.
 */
#[Fillable(['item_id', 'quantity_change', 'unit_cost'])]
class StockAdjustmentItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_change' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<StockAdjustment, $this>
     */
    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function isIncrease(): bool
    {
        return bccomp((string) $this->quantity_change, '0', 3) > 0;
    }
}
