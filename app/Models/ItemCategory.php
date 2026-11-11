<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ItemCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A grouping of items. Categories nest one level deep (a category may have a parent, but a parent has none).
 */
#[Fillable(['name', 'parent_id', 'description'])]
class ItemCategory extends Model
{
    /** @use HasFactory<ItemCategoryFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<ItemCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<ItemCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ItemCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * "Parent › Child" for nested categories.
     */
    public function fullName(): string
    {
        return $this->parent ? "{$this->parent->name} › {$this->name}" : $this->name;
    }
}
