<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a quotation: a catalogue item or a free-text service. The tax rate value used is stored on the
 * line, so later changes to tax rates never alter an existing quotation.
 */
#[Fillable([
    'item_id', 'description', 'quantity', 'unit', 'unit_price', 'discount_amount', 'document_discount_share',
    'tax_rate_id', 'tax_rate', 'gross_amount', 'taxable_amount', 'tax_amount', 'line_total', 'sort_order',
])]
class QuotationItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'document_discount_share' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'gross_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /**
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
