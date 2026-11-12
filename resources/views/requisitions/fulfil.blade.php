@php
    $quantity = App\Support\Quantity::class;
    $open = $requisition->items->filter(fn ($line) => bccomp($line->remainingQuantity(), '0', 3) > 0);
    $hasStock = $open->contains(fn ($line) => $line->fulfilmentType() === App\Enums\FulfilmentType::StockIssue);
    $hasPurchases = $open->contains(fn ($line) => $line->fulfilmentType() === App\Enums\FulfilmentType::Purchase);
@endphp
<x-layouts::app :title="'Fulfil '.$requisition->number">
    <x-page-header :title="'Fulfil requisition '.$requisition->number" :subtitle="$requisition->department?->name"
        :breadcrumbs="['Requisitions' => route('requisitions.index'), $requisition->number => route('requisitions.show', $requisition), 'Fulfil' => null]" />

    <form method="POST" action="{{ route('requisitions.fulfil.store', $requisition) }}" novalidate>
        @csrf

        <div class="card mb-3">
            <div class="card-header fw-semibold">What was delivered</div>
            <div class="card-body pb-0">
                <p class="small text-secondary">Catalogue stock items are issued from the warehouse below. Everything else is recorded as purchased: one draft expense is created for Finance to record. Leave a quantity empty to deliver that line later.</p>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Line</th>
                            <th scope="col">How</th>
                            <th scope="col" class="text-end">Still to deliver</th>
                            <th scope="col" style="width: 10rem;">Deliver now</th>
                            <th scope="col" style="width: 11rem;">Actual unit cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($open as $line)
                            @php($isStock = $line->fulfilmentType() === App\Enums\FulfilmentType::StockIssue)
                            <tr>
                                <td>
                                    {{ $line->description }}
                                    @if ($line->item)<div class="small text-secondary"><code>{{ $line->item->code }}</code></div>@endif
                                </td>
                                <td class="small">
                                    {{ $line->fulfilmentType()->label() }}
                                    @if ($isStock)<div class="text-secondary">On hand (all warehouses): {{ $quantity::format($stockOnHand->get($line->item_id, '0')) }}</div>@endif
                                </td>
                                <td class="text-end">{{ $quantity::format($line->remainingQuantity()) }} {{ $line->unit }}</td>
                                <td>
                                    <input type="number" name="lines[{{ $line->id }}][quantity]" value="{{ old("lines.{$line->id}.quantity") }}" step="0.001" min="0" max="{{ $line->remainingQuantity() }}"
                                        @class(['form-control', 'is-invalid' => $errors->has("lines.{$line->id}.quantity")]) aria-label="Quantity to deliver for {{ $line->description }}">
                                </td>
                                <td>
                                    @if ($isStock)
                                        <span class="small text-secondary">average cost</span>
                                    @else
                                        <input type="number" name="lines[{{ $line->id }}][unit_cost]" value="{{ old("lines.{$line->id}.unit_cost", (string) $line->estimated_unit_cost) }}" step="0.01" min="0"
                                            @class(['form-control', 'is-invalid' => $errors->has("lines.{$line->id}.unit_cost")]) aria-label="Actual unit cost for {{ $line->description }}">
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-secondary small">Every approved line has been delivered.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-3">
            @if ($hasStock)
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Stock issue</div>
                        <div class="card-body">
                            <x-form.select name="warehouse_id" label="Issue from warehouse" :options="$warehouses" :value="$defaultWarehouse" />
                        </div>
                    </div>
                </div>
            @endif
            @if ($hasPurchases)
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Purchase</div>
                        <div class="card-body">
                            <x-form.select name="expense_category_id" label="Expense category" :options="$categories" placeholder="Choose a category" />
                            <x-form.select name="supplier_id" label="Supplier" :options="$suppliers" placeholder="None / not known" />
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <x-form.input name="notes" label="Notes" maxlength="500" help="e.g. delivery note number or who collected the items." />
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" @disabled($open->isEmpty())>Record delivery</button>
                    <a href="{{ route('requisitions.show', $requisition) }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</x-layouts::app>
