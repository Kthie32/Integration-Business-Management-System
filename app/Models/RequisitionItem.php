<?php

namespace App\Models;

use App\Enums\FulfilmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One requested line: a catalogue item or free text. The approved quantity is set on final approval and
 * fulfilment can never exceed it.
 */
class RequisitionItem extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'estimated_unit_cost' => 'decimal:2',
            'estimated_total' => 'decimal:2',
            'approved_quantity' => 'decimal:3',
            'fulfilled_quantity' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Requisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /**
     * @return HasMany<RequisitionFulfilment, $this>
     */
    public function fulfilments(): HasMany
    {
        return $this->hasMany(RequisitionFulfilment::class);
    }

    /**
     * Approved but not yet delivered.
     */
    public function remainingQuantity(): string
    {
        return bcsub((string) ($this->approved_quantity ?? '0'), (string) $this->fulfilled_quantity, 3);
    }

    /**
     * Catalogue stock items are issued from a warehouse; everything else is purchased.
     */
    public function fulfilmentType(): FulfilmentType
    {
        return $this->item !== null && $this->item->isStocked() ? FulfilmentType::StockIssue : FulfilmentType::Purchase;
    }
}
