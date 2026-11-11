<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\GoodsReceiptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Inventory\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function __construct(private GoodsReceiptService $receipts) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', GoodsReceipt::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,posted,void'],
            'supplier' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $receipts = GoodsReceipt::query()
            ->with('supplier', 'warehouse')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('number', 'like', $like)->orWhere('supplier_invoice_ref', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['supplier'] ?? null, fn ($query, int $supplierId) => $query->where('supplier_id', $supplierId))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('received_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('received_at', '<=', $to))
            ->latest('received_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.goods-receipts.index', [
            'receipts' => $receipts,
            'filters' => $filters,
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => collect(GoodsReceiptStatus::cases())->mapWithKeys(fn (GoodsReceiptStatus $status): array => [$status->value => $status->label()]),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', GoodsReceipt::class);

        return view('inventory.goods-receipts.create', [
            'receipt' => new GoodsReceipt([
                'received_at' => now(),
                'warehouse_id' => Warehouse::default()?->id,
                'supplier_id' => $request->integer('supplier') ?: null,
            ]),
            'lines' => [],
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request): RedirectResponse
    {
        $receipt = $this->receipts->createDraft($request->receiptData(), $request->user());

        if ($request->boolean('post_now') && $request->user()->can('post', $receipt)) {
            $receipt = $this->receipts->post($receipt, $request->user());

            return redirect()->route('goods-receipts.show', $receipt)->with('success', "{$receipt->number} posted; stock has been updated.");
        }

        return redirect()->route('goods-receipts.show', $receipt)->with('success', 'Draft saved. Post it to add the stock.');
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        Gate::authorize('view', $goodsReceipt);

        return view('inventory.goods-receipts.show', [
            'receipt' => $goodsReceipt->load('supplier', 'warehouse', 'items.item', 'creator', 'poster', 'voider', 'statusHistories.user'),
        ]);
    }

    public function edit(GoodsReceipt $goodsReceipt): View
    {
        Gate::authorize('update', $goodsReceipt);

        return view('inventory.goods-receipts.edit', [
            'receipt' => $goodsReceipt,
            'lines' => $goodsReceipt->items->map(fn ($line): array => [
                'item_id' => $line->item_id,
                'quantity' => (string) $line->quantity,
                'unit_cost' => (string) $line->unit_cost,
            ])->all(),
            ...$this->formOptions(),
        ]);
    }

    public function update(StoreGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->receipts->updateDraft($goodsReceipt, $request->receiptData());

        return redirect()->route('goods-receipts.show', $goodsReceipt)->with('success', 'Draft updated.');
    }

    public function destroy(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        Gate::authorize('delete', $goodsReceipt);

        $this->receipts->deleteDraft($goodsReceipt);

        return redirect()->route('goods-receipts.index')->with('success', 'Draft deleted.');
    }

    public function post(Request $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        Gate::authorize('post', $goodsReceipt);

        $receipt = $this->receipts->post($goodsReceipt, $request->user());

        return redirect()->route('goods-receipts.show', $receipt)->with('success', "{$receipt->number} posted; stock has been updated.");
    }

    public function void(Request $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        Gate::authorize('void', $goodsReceipt);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $receipt = $this->receipts->void($goodsReceipt, $data['reason'], $request->user());

        return redirect()->route('goods-receipts.show', $receipt)->with('success', "{$receipt->number} voided; its stock was reversed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::query()->active()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'),
            'items' => Item::query()->active()->stocked()->orderBy('name')->get(['id', 'name', 'code', 'unit', 'cost_price']),
        ];
    }
}
