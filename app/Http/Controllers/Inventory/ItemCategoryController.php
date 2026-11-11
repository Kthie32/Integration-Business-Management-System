<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreItemCategoryRequest;
use App\Models\ItemCategory;
use App\Services\Inventory\ItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ItemCategoryController extends Controller
{
    public function __construct(private ItemService $items) {}

    public function index(): View
    {
        Gate::authorize('viewAny', ItemCategory::class);

        return view('inventory.categories.index', [
            'categories' => ItemCategory::query()->whereNull('parent_id')->with(['children' => fn ($query) => $query->withCount('items')->orderBy('name')])->withCount('items')->orderBy('name')->get(),
            'parents' => ItemCategory::query()->whereNull('parent_id')->orderBy('name')->pluck('name', 'id'),
            'category' => new ItemCategory,
        ]);
    }

    public function store(StoreItemCategoryRequest $request): RedirectResponse
    {
        $category = $this->items->saveCategory(new ItemCategory, $request->validated());

        return redirect()->route('item-categories.index')->with('success', "Category \"{$category->name}\" created.");
    }

    public function edit(ItemCategory $itemCategory): View
    {
        Gate::authorize('update', $itemCategory);

        return view('inventory.categories.edit', [
            'category' => $itemCategory,
            'parents' => ItemCategory::query()->whereNull('parent_id')->whereKeyNot($itemCategory->id)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(StoreItemCategoryRequest $request, ItemCategory $itemCategory): RedirectResponse
    {
        $this->items->saveCategory($itemCategory, $request->validated());

        return redirect()->route('item-categories.index')->with('success', 'Category updated.');
    }

    public function destroy(ItemCategory $itemCategory): RedirectResponse
    {
        Gate::authorize('delete', $itemCategory);

        $this->items->deleteCategory($itemCategory);

        return redirect()->route('item-categories.index')->with('success', "Category \"{$itemCategory->name}\" deleted.");
    }
}
