@inject('settings', App\Services\System\SettingsService::class)
@php($quantity = App\Support\Quantity::class)
<x-layouts::app title="Stock movements">
    <x-page-header title="Stock movements" subtitle="The permanent stock ledger. Entries are never edited; mistakes are corrected by reversal." />

    <x-filter-bar :action="route('stock.movements')" :reset-url="route('stock.movements')">
        <div class="col-md-3">
            <label for="item" class="form-label small">Item</label>
            <select name="item" id="item" class="form-select">
                <option value="">Any</option>
                @foreach ($items as $id => $label)
                    <option value="{{ $id }}" @selected((int) ($filters['item'] ?? 0) === $id)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="type" class="form-label small">Type</label>
            <select name="type" id="type" class="form-select">
                <option value="">Any</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="warehouse" class="form-label small">Warehouse</label>
            <select name="warehouse" id="warehouse" class="form-select">
                <option value="">Any</option>
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

    <x-data-table :empty="$movements->isEmpty()" empty-message="No movements found." empty-icon="arrow-left-right" :paginator="$movements">
        <x-slot:head>
            <tr>
                <th scope="col">Date</th>
                <th scope="col">Type</th>
                <th scope="col">Item</th>
                <th scope="col">Warehouse</th>
                <th scope="col">Reference</th>
                <th scope="col" class="text-end">Quantity</th>
                <th scope="col" class="text-end">Unit cost</th>
                <th scope="col">By</th>
                <th scope="col"></th>
            </tr>
        </x-slot:head>

        @foreach ($movements as $movement)
            <tr>
                <td class="small text-nowrap">{{ $settings->formatDate($movement->moved_at) }}</td>
                <td><x-status-badge :label="$movement->type->label()" :color="$movement->type->color()" /></td>
                <td class="small"><a href="{{ route('items.stock-card', $movement->item) }}" class="text-decoration-none">{{ $movement->item->name }}</a></td>
                <td class="small">{{ $movement->warehouse->name }}</td>
                <td class="small">
                    @include('inventory.stock._reference', ['movement' => $movement])
                    @if ($movement->notes)
                        <div class="text-secondary text-truncate" style="max-width: 16rem;">{{ $movement->notes }}</div>
                    @endif
                </td>
                <td @class(['text-end text-nowrap', 'text-success' => $movement->direction === 1, 'text-danger' => $movement->direction === -1])>
                    {{ $movement->direction === 1 ? '+' : '−' }}{{ $quantity::format($movement->quantity) }}
                </td>
                <td class="text-end small"><x-money :amount="(string) $movement->unit_cost" /></td>
                <td class="small">{{ $movement->creator?->name ?? 'System' }}</td>
                <td class="text-end">
                    @if ($movement->reversal)
                        <x-status-badge label="Reversed" color="secondary" />
                    @elseif (auth()->user()->can('reverse', $movement))
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseModal"
                            data-reverse-url="{{ route('stock.movements.reverse', $movement) }}"
                            data-reverse-label="{{ $movement->type->label() }} of {{ $quantity::format($movement->quantity) }} {{ $movement->item->unit }} {{ $movement->item->name }}">Reverse</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="modal fade" id="reverseModal" tabindex="-1" aria-labelledby="reverseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" class="modal-content" data-reverse-form novalidate>
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5" id="reverseModalLabel">Reverse movement</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small" data-reverse-text></p>
                    <p class="small text-secondary">An equal and opposite movement is recorded. Transfers are reversed in both warehouses.</p>
                    <label for="reverse_reason" class="form-label">Reason <span class="text-danger" aria-hidden="true">*</span></label>
                    <textarea name="reason" id="reverse_reason" class="form-control" rows="2" minlength="5" maxlength="300" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reverse</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
