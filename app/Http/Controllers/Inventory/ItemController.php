<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreItemRequest;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Models\TaxRate;
use App\Models\Warehouse;
use App\Services\Inventory\ItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function __construct(private ItemService $items) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Item::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'type' => ['nullable', 'in:stock,service'],
            'status' => ['nullable', 'in:active,inactive'],
            'stock' => ['nullable', 'in:low'],
        ]);

        $items = Item::query()
            ->with('category')
            ->withStockOnHand()
            ->search($filters['search'] ?? null)
            ->when($filters['category'] ?? null, fn ($query, int $categoryId) => $query->where(fn ($inner) => $inner
                ->where('item_category_id', $categoryId)
                ->orWhereIn('item_category_id', ItemCategory::query()->where('parent_id', $categoryId)->select('id'))))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(($filters['stock'] ?? null) === 'low', fn ($query) => $query->lowStock())
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.items.index', [
            'items' => $items,
            'filters' => $filters,
            'categories' => $this->categoryOptions(),
            'lowStockCount' => Item::query()->active()->lowStock()->count(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Item::class);

        return view('inventory.items.create', [
            'item' => new Item(['type' => ItemType::Stock, 'unit' => 'pcs', 'is_active' => true, 'tax_rate_id' => TaxRate::query()->where('is_default', true)->value('id')]),
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $item = $this->items->create($request->validated(), $request->user());

        return redirect()->route('items.show', $item)->with('success', "Item {$item->code} created.");
    }

    public function show(Request $request, Item $item): View
    {
        Gate::authorize('view', $item);

        $canSeeStock = $request->user()->can('viewStock', $item);

        return view('inventory.items.show', [
            'item' => $item->load('category.parent', 'taxRate'),
            'levels' => $canSeeStock ? $item->stockLevels()->with('warehouse')->get() : collect(),
            'recentMovements' => $canSeeStock
                ? $item->stockMovements()->with('warehouse')->latest('moved_at')->latest('id')->limit(10)->get()
                : collect(),
            'canSeeStock' => $canSeeStock,
            'activity' => $request->user()->can('audit.view')
                ? AuditLog::query()->forModel($item)->with('user')->latest('id')->limit(10)->get()
                : collect(),
        ]);
    }

    public function edit(Item $item): View
    {
        Gate::authorize('update', $item);

        return view('inventory.items.edit', ['item' => $item, ...$this->formOptions()]);
    }

    public function update(StoreItemRequest $request, Item $item): RedirectResponse
    {
        $this->items->update($item, $request->validated());

        return redirect()->route('items.show', $item)->with('success', 'Item updated.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        $this->items->delete($item);

        return redirect()->route('items.index')->with('success', "Item {$item->code} deleted.");
    }

    /**
     * Chronological movements with a running balance (FR-INV-10), for one warehouse or all of them.
     */
    public function stockCard(Request $request, Item $item): View
    {
        Gate::authorize('viewStock', $item);

        $filters = $request->validate([
            'warehouse' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($filters['from']) ? $filters['from'].' 00:00:00' : null;
        $to = isset($filters['to']) ? $filters['to'].' 23:59:59' : null;

        $base = StockMovement::query()
            ->where('item_id', $item->id)
            ->when($filters['warehouse'] ?? null, fn ($query, int $warehouseId) => $query->where('warehouse_id', $warehouseId));

        $opening = $from === null ? '0' : (string) ((clone $base)
            ->where('moved_at', '<', $from)
            ->selectRaw('COALESCE(SUM(direction * quantity), 0) AS balance')
            ->value('balance') ?? '0');

        $movements = (clone $base)
            ->with('warehouse', 'creator', 'reference')
            ->when($from, fn ($query) => $query->where('moved_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('moved_at', '<=', $to))
            ->orderBy('moved_at')
            ->orderBy('id')
            ->limit(1000)
            ->get();

        $running = $opening;
        $rows = $movements->map(function (StockMovement $movement) use (&$running): array {
            $running = bcadd($running, $movement->signedQuantity(), 3);

            return ['movement' => $movement, 'balance' => $running];
        });

        return view('inventory.items.stock-card', [
            'item' => $item,
            'rows' => $rows,
            'opening' => bcadd($opening, '0', 3),
            'closing' => $running,
            'filters' => $filters,
            'warehouses' => Warehouse::query()->orderBy('name')->pluck('name', 'id'),
            'truncated' => $movements->count() === 1000,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => $this->categoryOptions(),
            'taxRates' => TaxRate::query()->active()->orderByDesc('is_default')->orderBy('name')->get()
                ->mapWithKeys(fn (TaxRate $rate): array => [$rate->id => "{$rate->name} ({$rate->displayRate()})"]),
            'types' => collect(ItemType::cases())->mapWithKeys(fn (ItemType $type): array => [$type->value => $type->label()]),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function categoryOptions(): array
    {
        return ItemCategory::query()->with('parent')->get()
            ->sortBy(fn (ItemCategory $category): string => $category->fullName())
            ->mapWithKeys(fn (ItemCategory $category): array => [$category->id => $category->fullName()])
            ->all();
    }
}
