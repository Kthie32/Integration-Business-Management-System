@inject('settings', App\Services\System\SettingsService::class)
@php($quantity = App\Support\Quantity::class)
<x-layouts::app :title="'Stock card · '.$item->name">
    <x-page-header :title="'Stock card: '.$item->name" :subtitle="$item->code.' · quantities in '.$item->unit"
        :breadcrumbs="['Items' => route('items.index'), $item->name => route('items.show', $item), 'Stock card' => null]" />

    <x-filter-bar :action="route('items.stock-card', $item)" :reset-url="route('items.stock-card', $item)">
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
            <label for="from" class="form-label small">From</label>
            <input type="date" name="from" id="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <label for="to" class="form-label small">To</label>
            <input type="date" name="to" id="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
        </div>
    </x-filter-bar>

    @if ($truncated)
        <div class="alert alert-info small">Showing the first 1,000 movements. Narrow the date range to see the rest.</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Type</th>
                        <th scope="col">Warehouse</th>
                        <th scope="col">Reference</th>
                        <th scope="col" class="text-end">In</th>
                        <th scope="col" class="text-end">Out</th>
                        <th scope="col" class="text-end">Balance</th>
                        <th scope="col" class="text-end">Unit cost</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light">
                        <td colspan="6" class="small fw-semibold">Opening balance</td>
                        <td class="text-end fw-semibold">{{ $quantity::format($opening) }}</td>
                        <td></td>
                    </tr>
                    @forelse ($rows as $row)
                        @php($movement = $row['movement'])
                        <tr>
                            <td class="small text-nowrap">{{ $settings->formatDate($movement->moved_at) }}</td>
                            <td><x-status-badge :label="$movement->type->label()" :color="$movement->type->color()" /></td>
                            <td class="small">{{ $movement->warehouse->name }}</td>
                            <td class="small">
                                @include('inventory.stock._reference', ['movement' => $movement])
                                @if ($movement->notes)
                                    <div class="text-secondary text-truncate" style="max-width: 18rem;">{{ $movement->notes }}</div>
                                @endif
                            </td>
                            <td class="text-end text-success">{{ $movement->direction === 1 ? $quantity::format($movement->quantity) : '' }}</td>
                            <td class="text-end text-danger">{{ $movement->direction === -1 ? $quantity::format($movement->quantity) : '' }}</td>
                            <td class="text-end fw-semibold">{{ $quantity::format($row['balance']) }}</td>
                            <td class="text-end small"><x-money :amount="(string) $movement->unit_cost" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-secondary py-4">No movements in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="6" class="small fw-semibold">Closing balance</td>
                        <td class="text-end fw-semibold">{{ $quantity::format($closing) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-layouts::app>
