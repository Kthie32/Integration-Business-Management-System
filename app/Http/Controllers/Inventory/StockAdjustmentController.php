<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\StockAdjustmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockAdjustmentRequest;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(private StockAdjustmentService $adjustments) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StockAdjustment::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,cancelled'],
            'warehouse' => ['nullable', 'integer'],
        ]);

        $adjustments = StockAdjustment::query()
            ->with('warehouse', 'creator')
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['warehouse'] ?? null, fn ($query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.adjustments.index', [
            'adjustments' => $adjustments,
            'filters' => $filters,
            'warehouses' => Warehouse::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => collect(StockAdjustmentStatus::cases())->mapWithKeys(fn (StockAdjustmentStatus $status): array => [$status->value => $status->label()]),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', StockAdjustment::class);

        return view('inventory.adjustments.create', [
            'adjustment' => new StockAdjustment(['adjusted_at' => now(), 'warehouse_id' => Warehouse::default()?->id]),
            'warehouses' => Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'),
            'items' => Item::query()->stocked()->orderBy('name')->get(['id', 'name', 'code', 'unit', 'cost_price']),
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        $adjustment = $this->adjustments->create($request->adjustmentData(), $request->user());

        return redirect()->route('stock-adjustments.show', $adjustment)
            ->with('success', "{$adjustment->number} submitted. Stock changes once another authorised person approves it.");
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        Gate::authorize('view', $stockAdjustment);

        $stockAdjustment->load('warehouse', 'items.item', 'creator', 'approver', 'statusHistories.user');

        $available = $stockAdjustment->warehouse->stockLevels()
            ->whereIn('item_id', $stockAdjustment->items->pluck('item_id'))
            ->pluck('quantity', 'item_id');

        return view('inventory.adjustments.show', ['adjustment' => $stockAdjustment, 'available' => $available]);
    }

    public function approve(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        Gate::authorize('decide', $stockAdjustment);

        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);

        $this->adjustments->approve($stockAdjustment, $request->user(), $data['comment'] ?? null);

        return redirect()->route('stock-adjustments.show', $stockAdjustment)->with('success', "{$stockAdjustment->number} approved; stock has been updated.");
    }

    public function reject(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        Gate::authorize('decide', $stockAdjustment);

        $data = $request->validate(['comment' => ['required', 'string', 'min:5', 'max:500']]);

        $this->adjustments->reject($stockAdjustment, $request->user(), $data['comment']);

        return redirect()->route('stock-adjustments.show', $stockAdjustment)->with('success', "{$stockAdjustment->number} rejected.");
    }

    public function cancel(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        Gate::authorize('cancel', $stockAdjustment);

        $this->adjustments->cancel($stockAdjustment, $request->user());

        return redirect()->route('stock-adjustments.show', $stockAdjustment)->with('success', "{$stockAdjustment->number} cancelled.");
    }
}
