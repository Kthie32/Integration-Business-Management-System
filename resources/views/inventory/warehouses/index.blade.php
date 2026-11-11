<x-layouts::app title="Warehouses">
    <x-page-header title="Warehouses" subtitle="Stores and locations that hold stock.">
        @can('create', App\Models\Warehouse::class)
            <x-slot:actions>
                <a href="{{ route('warehouses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New warehouse</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-data-table :empty="$warehouses->isEmpty()" empty-message="No warehouses yet." empty-icon="building">
        <x-slot:head>
            <tr>
                <th scope="col">Warehouse</th>
                <th scope="col">Location</th>
                <th scope="col" class="text-end">Items in stock</th>
                @can('stock.view')<th scope="col" class="text-end">Stock value</th>@endcan
                <th scope="col">Status</th>
                <th scope="col"></th>
            </tr>
        </x-slot:head>

        @foreach ($warehouses as $warehouse)
            <tr>
                <td>
                    <span class="fw-semibold">{{ $warehouse->name }}</span>
                    @if ($warehouse->is_default)<x-status-badge label="Default" color="primary" class="ms-1" />@endif
                    <div class="small text-secondary"><code>{{ $warehouse->code }}</code></div>
                </td>
                <td class="small">{{ $warehouse->location ?? '—' }}</td>
                <td class="text-end">{{ $totals->get($warehouse->id)?->item_count ?? 0 }}</td>
                @can('stock.view')
                    <td class="text-end"><x-money :amount="(string) bcadd((string) ($totals->get($warehouse->id)?->stock_value ?? '0'), '0', 2)" /></td>
                @endcan
                <td>
                    @if ($warehouse->is_active)
                        <x-status-badge label="Active" color="success" />
                    @else
                        <x-status-badge label="Inactive" color="secondary" />
                    @endif
                </td>
                <td class="text-end text-nowrap">
                    @can('stock.view')
                        <a href="{{ route('stock.levels', ['warehouse' => $warehouse->id]) }}" class="btn btn-sm btn-outline-secondary">Stock</a>
                    @endcan
                    @can('update', $warehouse)
                        <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
