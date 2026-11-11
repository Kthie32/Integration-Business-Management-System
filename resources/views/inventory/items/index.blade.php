<x-layouts::app title="Items">
    <x-page-header title="Items" subtitle="Products and services you buy, stock and sell.">
        <x-slot:actions>
            @can('stock.receive')
                <a href="{{ route('goods-receipts.create') }}" class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-down me-1" aria-hidden="true"></i>Receive goods</a>
            @endcan
            @can('create', App\Models\Item::class)
                <a href="{{ route('items.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New item</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($lowStockCount > 0 && ($filters['stock'] ?? null) !== 'low')
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <div class="me-auto">{{ $lowStockCount }} item(s) are at or below their minimum stock level.</div>
            <a href="{{ route('items.index', ['stock' => 'low']) }}" class="alert-link">Show them</a>
        </div>
    @endif

    <x-filter-bar :action="route('items.index')" :reset-url="route('items.index')">
        <div class="col-md-4">
            <label for="search" class="form-label small">Search</label>
            <input type="search" name="search" id="search" class="form-control" placeholder="Name or code" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label for="category" class="form-label small">Category</label>
            <select name="category" id="category" class="form-select">
                <option value="">Any</option>
                @foreach ($categories as $id => $name)
                    <option value="{{ $id }}" @selected((int) ($filters['category'] ?? 0) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="type" class="form-label small">Type</label>
            <select name="type" id="type" class="form-select">
                <option value="">Any</option>
                <option value="stock" @selected(($filters['type'] ?? '') === 'stock')>Stock items</option>
                <option value="service" @selected(($filters['type'] ?? '') === 'service')>Services</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label small">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Any</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-md-auto">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="stock" value="low" id="stock" @checked(($filters['stock'] ?? '') === 'low')>
                <label class="form-check-label small" for="stock">Low stock only</label>
            </div>
        </div>
    </x-filter-bar>

    <x-data-table :empty="$items->isEmpty()" empty-message="No items found." empty-icon="box-seam" :paginator="$items">
        <x-slot:head>
            <tr>
                <th scope="col">Item</th>
                <th scope="col">Category</th>
                <th scope="col" class="text-end">Selling price</th>
                <th scope="col" class="text-end">On hand</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($items as $item)
            @php($isLow = $item->isStocked() && bccomp((string) $item->min_stock_level, '0', 3) > 0 && bccomp((string) $item->stock_on_hand, (string) $item->min_stock_level, 3) <= 0)
            <tr>
                <td>
                    <a href="{{ route('items.show', $item) }}" class="fw-semibold text-decoration-none">{{ $item->name }}</a>
                    <div class="small text-secondary"><code>{{ $item->code }}</code> · {{ $item->type->label() }}</div>
                </td>
                <td class="small">{{ $item->category?->name ?? '—' }}</td>
                <td class="text-end"><x-money :amount="(string) $item->selling_price" /></td>
                <td class="text-end">
                    @if ($item->isStocked())
                        <span @class(['fw-semibold text-danger' => $isLow])>{{ App\Support\Quantity::format($item->stock_on_hand) }}</span>
                        <span class="small text-secondary">{{ $item->unit }}</span>
                        @if ($isLow)
                            <div><x-status-badge label="Low stock" color="warning" /></div>
                        @endif
                    @else
                        <span class="text-secondary small">n/a</span>
                    @endif
                </td>
                <td>
                    @if ($item->is_active)
                        <x-status-badge label="Active" color="success" />
                    @else
                        <x-status-badge label="Inactive" color="secondary" />
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
