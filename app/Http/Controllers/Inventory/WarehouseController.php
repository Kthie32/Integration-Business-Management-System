<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\Inventory\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(private WarehouseService $warehouses) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Warehouse::class);

        $totals = StockLevel::query()
            ->where('quantity', '>', 0)
            ->groupBy('warehouse_id')
            ->selectRaw('warehouse_id, COUNT(*) AS item_count, COALESCE(SUM(quantity * average_cost), 0) AS stock_value')
            ->get()
            ->keyBy('warehouse_id');

        return view('inventory.warehouses.index', [
            'warehouses' => Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'totals' => $totals,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Warehouse::class);

        return view('inventory.warehouses.create', ['warehouse' => new Warehouse(['is_active' => true])]);
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $warehouse = $this->warehouses->save(new Warehouse, $request->validated());

        return redirect()->route('warehouses.index')->with('success', "Warehouse {$warehouse->name} created.");
    }

    public function edit(Warehouse $warehouse): View
    {
        Gate::authorize('update', $warehouse);

        return view('inventory.warehouses.edit', ['warehouse' => $warehouse]);
    }

    public function update(StoreWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->warehouses->save($warehouse, $request->validated());

        return redirect()->route('warehouses.index')->with('success', 'Warehouse updated.');
    }
}
