{{-- The line editor and totals of a sales document. Expects $document (quotation or invoice), $lineRows, $items, $taxRates, $discountTypes. --}}
@inject('settings', App\Services\System\SettingsService::class)
@php($defaultTaxRate = $taxRates->firstWhere('is_default', true))
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
                    @include('sales._line', ['index' => $index, 'line' => $line])
                @endforeach
            </tbody>
        </table>
    </div>
    <template data-line-template>
        @include('sales._line', ['index' => '__INDEX__', 'line' => ['item_id' => null, 'description' => null, 'quantity' => '1', 'unit' => null, 'unit_price' => null, 'discount_amount' => null, 'tax_rate_id' => $defaultTaxRate?->id]])
    </template>
    <div class="card-body">
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-add-line><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add line</button>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="row g-2">
                    <div class="col-sm-6">
                        <x-form.select name="document_discount_type" label="Overall discount" :options="$discountTypes" :value="$document->document_discount_type" placeholder="No discount" data-document-discount-type />
                    </div>
                    <div class="col-sm-6">
                        <x-form.input name="document_discount_value" label="Discount value" type="number" step="0.01" min="0"
                            :value="$document->document_discount_type ? (string) $document->document_discount_value : null" data-document-discount-value />
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

