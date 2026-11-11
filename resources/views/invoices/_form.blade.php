{{-- Shared by create and edit. Expects $invoice, $lines, $customers, $warehouses, $items, $taxRates, $discountTypes. --}}
@php
    $lineRows = old('lines', $lines);
    if (count($lineRows) === 0) {
        $lineRows = [['item_id' => null, 'description' => null, 'quantity' => '1', 'unit' => null, 'unit_price' => null, 'discount_amount' => null, 'tax_rate_id' => $taxRates->firstWhere('is_default', true)?->id]];
    }
    $customerOptions = $customers->mapWithKeys(fn ($customer) => [$customer->id => "{$customer->name} ({$customer->code}) · {$customer->payment_terms_days} days"]);
    $warehouseOptions = $warehouses->mapWithKeys(fn ($warehouse) => [$warehouse->id => $warehouse->label()]);
@endphp

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-lg-6">
                <x-form.select name="customer_id" label="Customer" :options="$customerOptions" :value="$invoice->customer_id" placeholder="Choose a customer" data-tom-select required />
                @can('create', App\Models\Customer::class)
                    <div class="form-text mt-n2 mb-3">Not listed? <a href="{{ route('customers.create') }}" target="_blank" rel="noopener">Add a customer</a> in a new tab, then reload this page.</div>
                @endcan
            </div>
            <div class="col-sm-6 col-lg-3">
                <x-form.input name="invoice_date" label="Invoice date" type="date" :value="$invoice->invoice_date?->toDateString()" required />
            </div>
            <div class="col-sm-6 col-lg-3">
                <x-form.input name="due_date" label="Due date" type="date" :value="$invoice->due_date?->toDateString()"
                    help="Leave empty to use the customer's payment terms." />
            </div>
            <div class="col-lg-6">
                <x-form.select name="warehouse_id" label="Goods leave from" :options="$warehouseOptions" :value="$invoice->warehouse_id" placeholder="No stocked items"
                    help="Stocked items are taken out of this warehouse when the invoice is issued." />
            </div>
        </div>
    </div>
</div>

@include('sales._lines-card', ['document' => $invoice, 'lineRows' => $lineRows])

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-lg-6">
                <x-form.textarea name="notes" label="Notes to the customer" :value="$invoice->notes" rows="3" maxlength="5000" />
            </div>
            <div class="col-lg-6">
                <x-form.textarea name="terms" label="Payment terms and conditions" :value="$invoice->terms" rows="3" maxlength="5000" />
            </div>
        </div>
    </div>
</div>
