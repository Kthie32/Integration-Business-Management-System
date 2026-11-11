{{-- Shared by create and edit. Expects $receipt, $lines, $suppliers, $warehouses, $items. --}}
@php
    $lineRows = old('lines', $lines);
    if (count($lineRows) === 0) {
        $lineRows = [['item_id' => null, 'quantity' => null, 'unit_cost' => null]];
    }
@endphp

<div class="row">
    <div class="col-md-5">
        <x-form.select name="supplier_id" label="Supplier" :options="$suppliers" :value="$receipt->supplier_id" placeholder="Choose a supplier" required />
    </div>
    <div class="col-md-4">
        <x-form.select name="warehouse_id" label="Received into" :options="$warehouses" :value="$receipt->warehouse_id" required />
    </div>
    <div class="col-md-3">
        <x-form.input name="received_at" label="Date received" type="date" :value="$receipt->received_at?->toDateString()" :max="now()->toDateString()" required />
    </div>
</div>

<div class="row">
    <div class="col-md-5">
        <x-form.input name="supplier_invoice_ref" label="Supplier invoice / delivery note no." :value="$receipt->supplier_invoice_ref" maxlength="60" />
    </div>
</div>

<h2 class="h6 mt-2 mb-2">Items received</h2>
@error('lines')
    <div class="alert alert-danger small py-2">{{ $message }}</div>
@enderror

<div data-line-editor>
    <div class="table-responsive">
        <table class="table align-middle mb-2">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="min-width: 16rem;">Item</th>
                    <th scope="col" style="width: 9rem;">Quantity</th>
                    <th scope="col" style="width: 11rem;">Unit cost</th>
                    <th scope="col" class="text-end" style="width: 10rem;">Line total</th>
                    <th scope="col" style="width: 3rem;"><span class="visually-hidden">Remove</span></th>
                </tr>
            </thead>
            <tbody data-lines>
                @foreach ($lineRows as $index => $line)
                    @include('inventory.goods-receipts._line', ['index' => $index, 'line' => $line])
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end fw-semibold">Total</td>
                    <td class="text-end fw-semibold" data-grand-total>0</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <template data-line-template>
        @include('inventory.goods-receipts._line', ['index' => '__INDEX__', 'line' => ['item_id' => null, 'quantity' => null, 'unit_cost' => null]])
    </template>
    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-add-line><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add item</button>
</div>

<x-form.textarea name="notes" label="Notes" :value="$receipt->notes" rows="2" maxlength="2000" />
