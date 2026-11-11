<?php

namespace App\Models;

use App\Enums\ItemType;
use App\Models\Concerns\Auditable;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A product or service the organization buys, stocks or sells. Codes come from the numbering sequence ("ITM-0001").
 */
#[Fillable(['name', 'description', 'item_category_id', 'type', 'unit', 'selling_price', 'cost_price', 'tax_rate_id', 'min_stock_level', 'reorder_qty', 'is_active'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'selling_price' => 'decimal:4',
            'cost_price' => 'decimal:4',
            'min_stock_level' => 'decimal:3',
            'reorder_qty' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ItemCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    /**
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isStocked(): bool
    {
        return $this->type === ItemType::Stock;
    }

    /**
     * @param  Builder<Item>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Item>  $query
     */
    public function scopeStocked(Builder $query): void
    {
        $query->where('type', ItemType::Stock->value);
    }

    /**
     * Adds a "stock_on_hand" column: the item's total quantity across all warehouses.
     *
     * @param  Builder<Item>  $query
     */
    public function scopeWithStockOnHand(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('items.*');
        }

        $query->addSelect([
            'stock_on_hand' => StockLevel::query()
                ->selectRaw('COALESCE(SUM(quantity), 0)')
                ->whereColumn('stock_levels.item_id', 'items.id'),
        ]);
    }

    /**
     * Stock items with a minimum level whose total quantity is at or below it (FR-INV-09).
     *
     * @param  Builder<Item>  $query
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->where('items.type', ItemType::Stock->value)
            ->where('items.min_stock_level', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM stock_levels WHERE stock_levels.item_id = items.id) <= items.min_stock_level');
    }

    /**
     * @param  Builder<Item>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $inner) => $inner->where('items.name', 'like', $like)->orWhere('items.code', 'like', $like));
    }

    /**
     * Total quantity on hand across all warehouses, as a decimal string.
     */
    public function totalStock(): string
    {
        return (string) ($this->stockLevels()->sum('quantity') ?: '0');
    }

    /**
     * Whether any stock movement, goods receipt or adjustment refers to this item. Such items are deactivated, not deleted.
     */
    public function hasTransactions(): bool
    {
        return $this->stockMovements()->exists()
            || GoodsReceiptItem::query()->where('item_id', $this->id)->exists()
            || StockAdjustmentItem::query()->where('item_id', $this->id)->exists();
    }

    /**
     * "Name (CODE)" for selection lists.
     */
    public function label(): string
    {
        return "{$this->name} ({$this->code})";
    }
}
