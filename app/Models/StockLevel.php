<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The current quantity and weighted average cost of one item in one warehouse.
 *
 * Only the StockService changes these rows, in the same transaction as the movement that causes the change.
 */
class StockLevel extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'average_cost' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Quantity × average cost, rounded to money precision.
     */
    public function value(): string
    {
        return bcadd(bcmul((string) $this->quantity, (string) $this->average_cost, 6), '0', 2);
    }
}
