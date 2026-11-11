@php
    $titles = ['issue' => 'Issue stock', 'transfer' => 'Transfer stock', 'opening' => 'Record opening balance'];
    $intros = [
        'issue' => 'Take stock out of a warehouse for internal use. It leaves at the current average cost.',
        'transfer' => 'Move stock from one warehouse to another. It arrives at the sending warehouse\'s average cost.',
        'opening' => 'Enter the quantity an item already had when you started using the system. Only allowed before any other movement of the item in that warehouse.',
    ];
@endphp
<x-layouts::app :title="$titles[$operation]">
    <x-page-header :title="$titles[$operation]" :subtitle="$intros[$operation]" :breadcrumbs="['Stock levels' => route('stock.levels'), $titles[$operation] => null]" />

    <div class="card" style="max-width: 48rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('stock.operations.store', $operation) }}" novalidate>
                @csrf

                <x-form.select name="item_id" label="Item" :options="$items" :value="$selectedItemId" placeholder="Choose an item" required />

                <div class="row">
                    <div class="col-md-6">
                        <x-form.select name="warehouse_id" :label="$operation === 'transfer' ? 'From warehouse' : 'Warehouse'" :options="$warehouses" :value="$defaultWarehouseId" required />
                    </div>
                    @if ($operation === 'transfer')
                        <div class="col-md-6">
                            <x-form.select name="to_warehouse_id" label="To warehouse" :options="$warehouses" placeholder="Choose" required />
                        </div>
                    @endif
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <x-form.input name="quantity" label="Quantity" type="number" step="0.001" min="0.001" required />
                    </div>
                    @if ($operation === 'opening')
                        <div class="col-md-4">
                            <x-form.input name="unit_cost" label="Unit cost" type="number" step="0.01" min="0" required />
                        </div>
                    @endif
                    <div class="col-md-4">
                        <x-form.input name="moved_at" label="Date" type="date" :value="now()->toDateString()" :max="now()->toDateString()" required />
                    </div>
                </div>

                @if ($operation === 'issue')
                    <x-form.textarea name="notes" label="Purpose / issued to" rows="2" maxlength="500" required />
                @else
                    <x-form.textarea name="notes" label="Notes" rows="2" maxlength="500" />
                @endif

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ $titles[$operation] }}</button>
                    <a href="{{ route('stock.levels') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
