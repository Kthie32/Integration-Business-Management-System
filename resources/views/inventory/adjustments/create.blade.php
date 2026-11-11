@php
    $lineRows = old('lines', []);
    if (count($lineRows) === 0) {
        $lineRows = [['item_id' => null, 'quantity_change' => null, 'unit_cost' => null]];
    }
@endphp
<x-layouts::app title="New stock adjustment">
    <x-page-header title="New stock adjustment"
        subtitle="Enter a positive change to add stock or a negative change to remove it. Stock changes only after another authorised person approves."
        :breadcrumbs="['Stock adjustments' => route('stock-adjustments.index'), 'New' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('stock-adjustments.store') }}" novalidate>
                @csrf

                <div class="row">
                    <div class="col-md-5">
                        <x-form.select name="warehouse_id" label="Warehouse" :options="$warehouses" :value="$adjustment->warehouse_id" required />
                    </div>
                    <div class="col-md-3">
                        <x-form.input name="adjusted_at" label="Date" type="date" :value="$adjustment->adjusted_at?->toDateString()" :max="now()->toDateString()" required />
                    </div>
                </div>

                <x-form.textarea name="reason" label="Reason" rows="2" maxlength="1000" required help="e.g. stock count on 30 Nov found 3 fewer; 2 cartons water-damaged." />

                <h2 class="h6 mt-2 mb-2">Items</h2>
                @error('lines')
                    <div class="alert alert-danger small py-2">{{ $message }}</div>
                @enderror

                <div data-line-editor>
                    <div class="table-responsive">
                        <table class="table align-middle mb-2">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" style="min-width: 16rem;">Item</th>
                                    <th scope="col" style="width: 11rem;">Change (+/−)</th>
                                    <th scope="col" style="width: 12rem;">Unit cost (increases)</th>
                                    <th scope="col" style="width: 3rem;"><span class="visually-hidden">Remove</span></th>
                                </tr>
                            </thead>
                            <tbody data-lines>
                                @foreach ($lineRows as $index => $line)
                                    @include('inventory.adjustments._line', ['index' => $index, 'line' => $line])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <template data-line-template>
                        @include('inventory.adjustments._line', ['index' => '__INDEX__', 'line' => []])
                    </template>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-add-line><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add item</button>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Submit for approval</button>
                    <a href="{{ route('stock-adjustments.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
