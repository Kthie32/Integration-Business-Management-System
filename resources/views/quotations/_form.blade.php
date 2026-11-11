{{-- Shared by create and edit. Expects $quotation, $lines, $customers, $items, $taxRates, $discountTypes. --}}
@php
    $lineRows = old('lines', $lines);
    if (count($lineRows) === 0) {
        $lineRows = [['item_id' => null, 'description' => null, 'quantity' => '1', 'unit' => null, 'unit_price' => null, 'discount_amount' => null, 'tax_rate_id' => $taxRates->firstWhere('is_default', true)?->id]];
    }
    $customerOptions = $customers->mapWithKeys(fn ($customer) => [$customer->id => "{$customer->name} ({$customer->code})"]);
@endphp

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-lg-6">
                <x-form.select name="customer_id" label="Customer" :options="$customerOptions" :value="$quotation->customer_id" placeholder="Choose a customer" data-tom-select required />
                @can('create', App\Models\Customer::class)
                    <div class="form-text mt-n2 mb-3">Not listed? <a href="{{ route('customers.create') }}" target="_blank" rel="noopener">Add a customer</a> in a new tab, then reload this page.</div>
                @endcan
            </div>
            <div class="col-sm-6 col-lg-3">
                <x-form.input name="quote_date" label="Quotation date" type="date" :value="$quotation->quote_date?->toDateString()" required />
            </div>
            <div class="col-sm-6 col-lg-3">
                <x-form.input name="valid_until" label="Valid until" type="date" :value="$quotation->valid_until?->toDateString()" required />
            </div>
        </div>
    </div>
</div>

@include('sales._lines-card', ['document' => $quotation, 'lineRows' => $lineRows])

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-lg-6">
                <x-form.textarea name="notes" label="Notes to the customer" :value="$quotation->notes" rows="3" maxlength="5000" />
            </div>
            <div class="col-lg-6">
                <x-form.textarea name="terms" label="Terms and conditions" :value="$quotation->terms" rows="3" maxlength="5000" />
            </div>
        </div>
    </div>
</div>
