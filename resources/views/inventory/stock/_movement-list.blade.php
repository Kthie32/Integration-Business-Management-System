{{-- A compact list of stock movements. Expects $movements and $showItem. --}}
@inject('settings', App\Services\System\SettingsService::class)

@if ($movements->isEmpty())
    <div class="card-body text-secondary small">No movements yet.</div>
@else
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Type</th>
                    @if ($showItem)<th scope="col">Item</th>@endif
                    <th scope="col">Warehouse</th>
                    <th scope="col" class="text-end">Quantity</th>
                    <th scope="col" class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td class="small text-nowrap">{{ $settings->formatDate($movement->moved_at) }}</td>
                        <td><x-status-badge :label="$movement->type->label()" :color="$movement->type->color()" /></td>
                        @if ($showItem)<td class="small">{{ $movement->item->name }}</td>@endif
                        <td class="small">{{ $movement->warehouse->name }}</td>
                        <td @class(['text-end', 'text-success' => $movement->direction === 1, 'text-danger' => $movement->direction === -1])>
                            {{ $movement->direction === 1 ? '+' : '−' }}{{ App\Support\Quantity::format($movement->quantity) }}
                        </td>
                        <td class="text-end">{{ App\Support\Quantity::format($movement->balance_after) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
