<?php

namespace App\Services\Inventory;

use App\Enums\DocumentType;
use App\Exceptions\BusinessRuleException;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use App\Services\System\NumberingService;
use Illuminate\Support\Facades\DB;

/**
 * Creates, edits and removes items and item categories. Item codes come from the numbering sequence ("ITM-0001").
 */
class ItemService
{
    public function __construct(private NumberingService $numbering) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Item
    {
        return DB::transaction(function () use ($data, $actor): Item {
            $item = new Item($data);
            $item->forceFill([
                'code' => $this->numbering->next(DocumentType::Item),
                'created_by' => $actor->id,
            ])->save();

            return $item;
        });
    }

    /**
     * An item's type cannot change once it has stock history, because service items carry no stock.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Item $item, array $data): Item
    {
        if (isset($data['type']) && $data['type'] !== $item->type->value && $item->hasTransactions()) {
            throw new BusinessRuleException('The item type cannot change after stock has moved. Create a new item instead.');
        }

        $item->update($data);

        return $item;
    }

    /**
     * Items with transactions keep their history and can only be deactivated.
     */
    public function delete(Item $item): void
    {
        if ($item->hasTransactions()) {
            throw new BusinessRuleException('This item has stock history and cannot be deleted. Deactivate it instead.');
        }

        $item->delete();
    }

    /**
     * @param  array{name: string, parent_id?: int|null, description?: string|null}  $data
     */
    public function saveCategory(ItemCategory $category, array $data): ItemCategory
    {
        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            $parent = ItemCategory::query()->findOrFail($parentId);

            if ($parent->is($category) || $parent->parent_id !== null) {
                throw new BusinessRuleException('Categories can only be nested one level deep.');
            }

            if ($category->exists && $category->children()->exists()) {
                throw new BusinessRuleException('This category has sub-categories, so it cannot be placed under another category.');
            }
        }

        $category->fill($data)->save();

        return $category;
    }

    public function deleteCategory(ItemCategory $category): void
    {
        if ($category->items()->withTrashed()->exists() || $category->children()->exists()) {
            throw new BusinessRuleException('Only an empty category can be deleted. Move its items and sub-categories first.');
        }

        $category->delete();
    }
}
