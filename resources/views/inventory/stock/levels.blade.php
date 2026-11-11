@php($quantity = App\Support\Quantity::class)
<x-layouts::app title="Stock levels">
    <x-page-header title="Stock levels" subtitle="Current quantity and weighted average cost of each item in each warehouse.">
        <x-slot:actions>
            @can('stock.issue')
                <a href="{{ route('stock.operations.create', 'issue') }}" class="btn btn-outline-primary">Issue</a>
            @endcan
            @can('stock.transfer')
                <a href="{{ route('stock.operations.create', 'transfer') }}" class="btn btn-outline-primary">Transfer</a>
            @endcan
            @can('stock.adjust')
                <a href="{{ route('stock.operations.create', 'opening') }}" class="btn btn-outline-secondary">Opening balance</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <x-stat-card label="Value of listed stock" :value="app(App\Services\System\SettingsService::class)->formatMoney($totalValue)" icon="cash-stack" />
        </div>
    </div>

    <x-filter-bar :action="route('stock.levels')" :reset-url="route('stock.levels')">
        <div class="col-md-4">
            <label for="search" class="form-label small">Item</label>
            <input type="search" name="search" id="search" class="form-control" placeholder="Name or code" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-3">
            <label for="warehouse" class="form-label small">Warehouse</label>
            <select name="warehouse" id="warehouse" class="form-select">
                <option value="">All warehouses</option>
                @foreach ($warehouses as $id => $name)
                    <option value="{{ $id }}" @selected((int) ($filters['warehouse'] ?? 0) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="show" class="form-label small">Show</label>
            <select name="show" id="show" class="form-select">
                <option value="in_stock" @selected(($filters['show'] ?? 'in_stock') === 'in_stock')>In stock</option>
                <option value="all" @selected(($filters['show'] ?? '') === 'all')>Including zero</option>
            </select>
        </div>
    </x-filter-bar>

    <x-data-table :empty="$levels->isEmpty()" empty-message="No stock found." empty-icon="stack" :paginator="$levels">
        <x-slot:head>
            <tr>
                <th scope="col">Item</th>
                <th scope="col">Warehouse</th>
                <th scope="col" class="text-end">Quantity</th>
                <th scope="col" class="text-end">Average cost</th>
                <th scope="col" class="text-end">Value</th>
            </tr>
        </x-slot:head>

        @foreach ($levels as $level)
            <tr>
                <td>
                    <a href="{{ route('items.stock-card', [$level->item, 'warehouse' => $level->warehouse_id]) }}" class="fw-semibold text-decoration-none">{{ $level->item->name }}</a>
                    <div class="small text-secondary"><code>{{ $level->item->code }}</code></div>
                </td>
                <td class="small">{{ $level->warehouse->name }}</td>
                <td class="text-end">{{ $quantity::format($level->quantity) }} <span class="small text-secondary">{{ $level->item->unit }}</span></td>
                <td class="text-end"><x-money :amount="(string) $level->average_cost" /></td>
                <td class="text-end"><x-money :amount="$level->value()" /></td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
