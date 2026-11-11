@php($quantity = App\Support\Quantity::class)
<x-layouts::app :title="$item->name">
    <x-page-header :title="$item->name" :subtitle="$item->code.' · '.$item->type->label()"
        :breadcrumbs="['Items' => route('items.index'), $item->name => null]">
        <x-slot:actions>
            @if ($canSeeStock && $item->isStocked())
                <a href="{{ route('items.stock-card', $item) }}" class="btn btn-outline-secondary"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>Stock card</a>
            @endif
            @if ($item->isStocked() && $item->is_active)
                <div class="dropdown">
                    @canany(['stock.issue', 'stock.transfer', 'stock.adjust'])
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Stock actions</button>
                        <ul class="dropdown-menu">
                            @can('stock.issue')
                                <li><a class="dropdown-item" href="{{ route('stock.operations.create', ['operation' => 'issue', 'item' => $item->id]) }}">Issue stock</a></li>
                            @endcan
                            @can('stock.transfer')
                                <li><a class="dropdown-item" href="{{ route('stock.operations.create', ['operation' => 'transfer', 'item' => $item->id]) }}">Transfer stock</a></li>
                            @endcan
                            @can('stock.adjust')
                                <li><a class="dropdown-item" href="{{ route('stock.operations.create', ['operation' => 'opening', 'item' => $item->id]) }}">Record opening balance</a></li>
                                <li><a class="dropdown-item" href="{{ route('stock-adjustments.create') }}">New stock adjustment</a></li>
                            @endcan
                        </ul>
                    @endcanany
                </div>
            @endif
            @can('update', $item)
                <a href="{{ route('items.edit', $item) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a>
            @endcan
            @can('delete', $item)
                <form method="POST" action="{{ route('items.destroy', $item) }}" data-confirm="Delete {{ $item->name }}? This cannot be undone.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Delete</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header fw-semibold d-flex align-items-center">
                    Details
                    <span class="ms-auto">
                        @if ($item->is_active)
                            <x-status-badge label="Active" color="success" />
                        @else
                            <x-status-badge label="Inactive" color="secondary" />
                        @endif
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-5 text-secondary fw-normal">Category</dt><dd class="col-sm-7">{{ $item->category?->fullName() ?? '—' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Unit</dt><dd class="col-sm-7">{{ $item->unit }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Selling price</dt><dd class="col-sm-7"><x-money :amount="(string) $item->selling_price" /></dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Standard cost</dt><dd class="col-sm-7"><x-money :amount="(string) $item->cost_price" /></dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Tax</dt><dd class="col-sm-7">{{ $item->taxRate ? $item->taxRate->name.' ('.$item->taxRate->displayRate().')' : 'No tax' }}</dd>
                        @if ($item->isStocked())
                            <dt class="col-sm-5 text-secondary fw-normal">Minimum level</dt><dd class="col-sm-7">{{ $quantity::format($item->min_stock_level) }} {{ $item->unit }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Reorder quantity</dt><dd class="col-sm-7">{{ $item->reorder_qty !== null ? $quantity::format($item->reorder_qty).' '.$item->unit : '—' }}</dd>
                        @endif
                    </dl>
                    @if ($item->description)
                        <hr>
                        <p class="small mb-0" style="white-space: pre-line">{{ $item->description }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            @if (! $item->isStocked())
                <div class="card h-100">
                    <div class="card-body text-center text-secondary py-5">
                        <i class="bi bi-tools fs-2 d-block mb-2" aria-hidden="true"></i>
                        This is a service item, so it carries no stock.
                    </div>
                </div>
            @elseif ($canSeeStock)
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Stock by warehouse</div>
                    @if ($levels->isEmpty())
                        <div class="card-body text-secondary small">No stock has been recorded for this item yet.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th scope="col">Warehouse</th><th scope="col" class="text-end">Quantity</th><th scope="col" class="text-end">Average cost</th><th scope="col" class="text-end">Value</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($levels as $level)
                                        <tr>
                                            <td>{{ $level->warehouse->name }}</td>
                                            <td class="text-end">{{ $quantity::format($level->quantity) }} {{ $item->unit }}</td>
                                            <td class="text-end"><x-money :amount="(string) $level->average_cost" /></td>
                                            <td class="text-end"><x-money :amount="$level->value()" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="fw-semibold">
                                        <td>Total</td>
                                        <td class="text-end">{{ $quantity::format($levels->reduce(fn ($carry, $level) => bcadd($carry, (string) $level->quantity, 3), '0')) }} {{ $item->unit }}</td>
                                        <td></td>
                                        <td class="text-end"><x-money :amount="$levels->reduce(fn ($carry, $level) => bcadd($carry, $level->value(), 2), '0')" /></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="card">
                    <div class="card-header fw-semibold d-flex align-items-center">
                        Recent movements
                        <a href="{{ route('items.stock-card', $item) }}" class="ms-auto small">Full stock card</a>
                    </div>
                    @include('inventory.stock._movement-list', ['movements' => $recentMovements, 'showItem' => false])
                </div>
            @endif
        </div>

        @if ($activity->isNotEmpty())
            <div class="col-12">
                @include('partials.recent-activity', ['activity' => $activity])
            </div>
        @endif
    </div>
</x-layouts::app>
