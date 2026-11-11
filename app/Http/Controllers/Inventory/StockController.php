<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockOperationRequest;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Stock balances, the movement ledger, and direct stock operations (issue, transfer, opening balance, reversal).
 */
class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function levels(Request $request): View
    {
        Gate::authorize('viewAny', StockMovement::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'warehouse' => ['nullable', 'integer'],
            'show' => ['nullable', 'in:all,in_stock'],
        ]);

        $query = StockLevel::query()
            ->join('items', 'items.id', '=', 'stock_levels.item_id')
            ->whereNull('items.deleted_at')
            ->when($filters['warehouse'] ?? null, fn ($query, int $warehouseId) => $query->where('stock_levels.warehouse_id', $warehouseId))
            ->when(($filters['show'] ?? 'in_stock') === 'in_stock', fn ($query) => $query->where('stock_levels.quantity', '>', 0))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('items.name', 'like', $like)->orWhere('items.code', 'like', $like));
            });

        $totalValue = (clone $query)->selectRaw('COALESCE(SUM(stock_levels.quantity * stock_levels.average_cost), 0) AS total')->value('total');

        $levels = $query->select('stock_levels.*')
            ->with('item', 'warehouse')
            ->orderBy('items.name')
            ->paginate(30)
            ->withQueryString();

        return view('inventory.stock.levels', [
            'levels' => $levels,
            'totalValue' => (string) $totalValue,
            'filters' => $filters,
            'warehouses' => Warehouse::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function movements(Request $request): View
    {
        Gate::authorize('viewAny', StockMovement::class);

        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:30'],
            'item' => ['nullable', 'integer'],
            'warehouse' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $movements = StockMovement::query()
            ->with('item', 'warehouse', 'creator', 'reference', 'reversal')
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['item'] ?? null, fn ($query, int $itemId) => $query->where('item_id', $itemId))
            ->when($filters['warehouse'] ?? null, fn ($query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('moved_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('moved_at', '<=', $to.' 23:59:59'))
            ->latest('moved_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('inventory.stock.movements', [
            'movements' => $movements,
            'filters' => $filters,
            'types' => collect(StockMovementType::cases())->mapWithKeys(fn (StockMovementType $type): array => [$type->value => $type->label()]),
            'warehouses' => Warehouse::query()->orderBy('name')->pluck('name', 'id'),
            'items' => Item::query()->stocked()->orderBy('name')->get()->mapWithKeys(fn (Item $item): array => [$item->id => $item->label()]),
        ]);
    }

    /**
     * The form for an issue, transfer or opening balance.
     */
    public function create(Request $request, string $operation): View
    {
        Gate::authorize(match ($operation) {
            'issue' => 'stock.issue',
            'transfer' => 'stock.transfer',
            'opening' => 'stock.adjust',
        });

        $warehouses = Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id');

        return view('inventory.stock.operation', [
            'operation' => $operation,
            'items' => Item::query()->active()->stocked()->orderBy('name')->get()->mapWithKeys(fn (Item $item): array => [$item->id => $item->label()]),
            'warehouses' => $warehouses,
            'defaultWarehouseId' => Warehouse::default()?->id,
            'selectedItemId' => $request->integer('item') ?: null,
        ]);
    }

    public function store(StockOperationRequest $request, string $operation): RedirectResponse
    {
        $data = $request->validated();
        $item = Item::query()->findOrFail($data['item_id']);
        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);
        $movedAt = CarbonImmutable::parse($data['moved_at'])->setTimeFrom(now());

        $message = match ($operation) {
            'issue' => $this->issue($item, $warehouse, $data, $movedAt),
            'transfer' => $this->transfer($item, $warehouse, Warehouse::query()->findOrFail($data['to_warehouse_id']), $data, $movedAt),
            'opening' => $this->openingBalance($item, $warehouse, $data, $movedAt),
        };

        return redirect()->route('items.stock-card', $item)->with('success', $message);
    }

    public function reverse(Request $request, StockMovement $movement): RedirectResponse
    {
        Gate::authorize('reverse', $movement);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);

        $this->stock->reverse($movement, 'Reversed: '.$data['reason']);

        return back()->with('success', 'The movement was reversed.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function issue(Item $item, Warehouse $warehouse, array $data, CarbonImmutable $movedAt): string
    {
        $this->stock->issue($item, $warehouse, (string) $data['quantity'], StockMovementType::Issue, null, $data['notes'], $movedAt);

        return "Issued {$data['quantity']} {$item->unit} of {$item->name} from {$warehouse->name}.";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function transfer(Item $item, Warehouse $from, Warehouse $to, array $data, CarbonImmutable $movedAt): string
    {
        $this->stock->transfer($item, $from, $to, (string) $data['quantity'], $data['notes'] ?? null, $movedAt);

        return "Transferred {$data['quantity']} {$item->unit} of {$item->name} from {$from->name} to {$to->name}.";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function openingBalance(Item $item, Warehouse $warehouse, array $data, CarbonImmutable $movedAt): string
    {
        $this->stock->recordOpeningBalance($item, $warehouse, (string) $data['quantity'], (string) $data['unit_cost'], $data['notes'] ?? null, $movedAt);

        return "Opening balance of {$data['quantity']} {$item->unit} recorded for {$item->name} in {$warehouse->name}.";
    }
}
