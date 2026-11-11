{{-- Shared by create and edit. Expects $quotation, $lines, $customers, $items, $taxRates, $discountTypes. --}}
@inject('settings', App\Services\System\SettingsService::class)
@php
    $lineRows = old('lines', $lines);
    if (count($lineRows) === 0) {
        $lineRows = [['item_id' => null, 'description' => null, 'quantity' => '1', 'unit' => null, 'unit_price' => null, 'discount_amount' => null, 'tax_rate_id' => $taxRates->firstWhere('is_default', true)?->id]];
    }
    $defaultTaxRate = $taxRates->firstWhere('is_default', true);
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

<div class="card mb-4" data-sales-line-editor data-default-tax="{{ $defaultTaxRate?->id }}" data-decimals="{{ (int) $settings->get('currency', 'decimals', 0) }}">
    <div class="card-header fw-semibold">Lines</div>
    <div class="card-body pb-0">
        @error('lines')
            <div class="alert alert-danger small py-2">{{ $message }}</div>
        @enderror
        <p class="small text-secondary mb-2">Pick a catalogue item, or leave the item empty and type a description for a free-text service. Prices exclude tax.</p>
    </div>
    <div class="table-responsive">
        <table class="table align-top mb-0 sales-lines">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="min-width: 15rem;">Item / description</th>
                    <th scope="col" style="width: 7rem;">Qty</th>
                    <th scope="col" style="width: 6rem;">Unit</th>
                    <th scope="col" style="width: 9rem;">Unit price</th>
                    <th scope="col" style="width: 8rem;">Discount</th>
                    <th scope="col" style="width: 9rem;">Tax</th>
                    <th scope="col" class="text-end" style="width: 9rem;">Amount</th>
                    <th scope="col" style="width: 3rem;"><span class="visually-hidden">Remove</span></th>
                </tr>
            </thead>
            <tbody data-lines>
                @foreach ($lineRows as $index => $line)
                    @include('quotations._line', ['index' => $index, 'line' => $line])
                @endforeach
            </tbody>
        </table>
    </div>
    <template data-line-template>
        @include('quotations._line', ['index' => '__INDEX__', 'line' => ['item_id' => null, 'description' => null, 'quantity' => '1', 'unit' => null, 'unit_price' => null, 'discount_amount' => null, 'tax_rate_id' => $defaultTaxRate?->id]])
    </template>
    <div class="card-body">
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-add-line><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add line</button>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="row g-2">
                    <div class="col-sm-6">
                        <x-form.select name="document_discount_type" label="Overall discount" :options="$discountTypes" :value="$quotation->document_discount_type" placeholder="No discount" data-document-discount-type />
                    </div>
                    <div class="col-sm-6">
                        <x-form.input name="document_discount_value" label="Discount value" type="number" step="0.01" min="0"
                            :value="$quotation->document_discount_type ? (string) $quotation->document_discount_value : null" data-document-discount-value />
                    </div>
                </div>
                <p class="small text-secondary mb-0">The overall discount is shared across lines in proportion to their amounts, before tax.</p>
            </div>
            <div class="col-lg-6">
                <dl class="row mb-0 sales-totals">
                    <dt class="col-7 fw-normal text-secondary">Subtotal</dt><dd class="col-5 text-end mb-1" data-total="subtotal">0</dd>
                    <dt class="col-7 fw-normal text-secondary">Discounts</dt><dd class="col-5 text-end mb-1" data-total="discount">0</dd>
                    <dt class="col-7 fw-normal text-secondary">Tax</dt><dd class="col-5 text-end mb-1" data-total="tax">0</dd>
                    <dt class="col-7 fs-5">Total <span class="small text-secondary fw-normal">(estimate)</span></dt><dd class="col-5 text-end fs-5 fw-semibold mb-0" data-total="total">0</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

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
