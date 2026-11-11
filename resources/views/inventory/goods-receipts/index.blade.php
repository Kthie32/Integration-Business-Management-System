@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="Goods received">
    <x-page-header title="Goods received" subtitle="Deliveries from suppliers into your warehouses.">
        @can('create', App\Models\GoodsReceipt::class)
            <x-slot:actions>
                <a href="{{ route('goods-receipts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Receive goods</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-filter-bar :action="route('goods-receipts.index')" :reset-url="route('goods-receipts.index')">
        <div class="col-md-3">
            <label for="search" class="form-label small">Search</label>
            <input type="search" name="search" id="search" class="form-control" placeholder="GRN or supplier invoice no." value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-3">
            <label for="supplier" class="form-label small">Supplier</label>
            <select name="supplier" id="supplier" class="form-select">
                <option value="">Any</option>
                @foreach ($suppliers as $id => $name)
                    <option value="{{ $id }}" @selected((int) ($filters['supplier'] ?? 0) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label small">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Any</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="from" class="form-label small">From</label>
            <input type="date" name="from" id="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label for="to" class="form-label small">To</label>
            <input type="date" name="to" id="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
        </div>
    </x-filter-bar>

    <x-data-table :empty="$receipts->isEmpty()" empty-message="No goods received notes found." empty-icon="box-arrow-in-down" :paginator="$receipts">
        <x-slot:head>
            <tr>
                <th scope="col">GRN</th>
                <th scope="col">Date</th>
                <th scope="col">Supplier</th>
                <th scope="col">Warehouse</th>
                <th scope="col" class="text-end">Total</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($receipts as $receipt)
            <tr>
                <td>
                    <a href="{{ route('goods-receipts.show', $receipt) }}" class="fw-semibold text-decoration-none">{{ $receipt->displayNumber() }}</a>
                    @if ($receipt->supplier_invoice_ref)
                        <div class="small text-secondary">Supplier ref {{ $receipt->supplier_invoice_ref }}</div>
                    @endif
                </td>
                <td class="small text-nowrap">{{ $settings->formatDate($receipt->received_at) }}</td>
                <td class="small">{{ $receipt->supplier->name }}</td>
                <td class="small">{{ $receipt->warehouse->name }}</td>
                <td class="text-end"><x-money :amount="(string) $receipt->total" /></td>
                <td><x-status-badge :status="$receipt->status" /></td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
