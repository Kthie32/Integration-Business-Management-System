@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app title="Stock adjustments">
    <x-page-header title="Stock adjustments" subtitle="Corrections for count differences, damage and loss. Each needs approval by a second person.">
        @can('create', App\Models\StockAdjustment::class)
            <x-slot:actions>
                <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New adjustment</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-filter-bar :action="route('stock-adjustments.index')" :reset-url="route('stock-adjustments.index')">
        <div class="col-md-3">
            <label for="status" class="form-label small">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Any</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="warehouse" class="form-label small">Warehouse</label>
            <select name="warehouse" id="warehouse" class="form-select">
                <option value="">Any</option>
                @foreach ($warehouses as $id => $name)
                    <option value="{{ $id }}" @selected((int) ($filters['warehouse'] ?? 0) === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </x-filter-bar>

    <x-data-table :empty="$adjustments->isEmpty()" empty-message="No stock adjustments found." empty-icon="sliders" :paginator="$adjustments">
        <x-slot:head>
            <tr>
                <th scope="col">Number</th>
                <th scope="col">Date</th>
                <th scope="col">Warehouse</th>
                <th scope="col">Reason</th>
                <th scope="col" class="text-end">Lines</th>
                <th scope="col">Raised by</th>
                <th scope="col">Status</th>
            </tr>
        </x-slot:head>

        @foreach ($adjustments as $adjustment)
            <tr>
                <td><a href="{{ route('stock-adjustments.show', $adjustment) }}" class="fw-semibold text-decoration-none">{{ $adjustment->number }}</a></td>
                <td class="small text-nowrap">{{ $settings->formatDate($adjustment->adjusted_at) }}</td>
                <td class="small">{{ $adjustment->warehouse->name }}</td>
                <td class="small text-truncate" style="max-width: 20rem;">{{ $adjustment->reason }}</td>
                <td class="text-end">{{ $adjustment->items_count }}</td>
                <td class="small">{{ $adjustment->creator?->name ?? '—' }}</td>
                <td><x-status-badge :status="$adjustment->status" /></td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts::app>
