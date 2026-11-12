<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\FulfilRequisitionRequest;
use App\Models\ExpenseCategory;
use App\Models\Requisition;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Requisition\RequisitionFulfilmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Delivering approved requisition lines from stock or by purchase (FR-REQ-06).
 */
class RequisitionFulfilmentController extends Controller
{
    public function __construct(private RequisitionFulfilmentService $fulfilments) {}

    public function create(Requisition $requisition): View
    {
        Gate::authorize('fulfil', $requisition);

        $requisition->load('items.item', 'department');

        return view('requisitions.fulfil', [
            'requisition' => $requisition,
            'warehouses' => Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'),
            'defaultWarehouse' => Warehouse::default()?->id,
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'stockOnHand' => StockLevel::query()
                ->whereIn('item_id', $requisition->items->pluck('item_id')->filter())
                ->selectRaw('item_id, SUM(quantity) as quantity')
                ->groupBy('item_id')
                ->pluck('quantity', 'item_id'),
        ]);
    }

    public function store(FulfilRequisitionRequest $request, Requisition $requisition): RedirectResponse
    {
        $requisition = $this->fulfilments->fulfil($requisition, $request->fulfilmentData(), $request->user());

        return redirect()->route('requisitions.show', $requisition)
            ->with('success', "Delivery recorded against {$requisition->number}. Status: {$requisition->status->label()}.");
    }
}
