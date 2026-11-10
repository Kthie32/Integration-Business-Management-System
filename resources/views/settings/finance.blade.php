<x-layouts::app title="Currency & defaults">
    <x-page-header title="Settings" subtitle="Base currency and the defaults used when creating quotations and invoices."
        :breadcrumbs="['Settings' => route('settings.index'), 'Currency & defaults' => null]" />

    @include('settings._nav')

    <form method="POST" action="{{ route('settings.finance.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Base currency</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-4">
                                <x-form.input name="currency_code" label="Code" :value="$currency['code']" maxlength="3" required help="e.g. UGX" />
                            </div>
                            <div class="col-sm-4">
                                <x-form.input name="currency_symbol" label="Symbol" :value="$currency['symbol']" maxlength="10" required />
                            </div>
                            <div class="col-sm-4">
                                <x-form.select name="currency_decimals" label="Decimals shown" :value="$currency['decimals']" required
                                    :options="[0 => '0 (1,250)', 1 => '1 (1,250.0)', 2 => '2 (1,250.00)']" />
                            </div>
                        </div>
                        <p class="small text-secondary mb-0">Amounts are always stored with two decimals; this only changes how they are displayed.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Defaults</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6">
                                <x-form.input name="quotation_validity_days" label="Quotation validity (days)" type="number" min="1" max="365"
                                    :value="$finance['quotation_validity_days']" required />
                            </div>
                            <div class="col-sm-6">
                                <x-form.input name="invoice_payment_terms_days" label="Invoice payment terms (days)" type="number" min="0" max="365"
                                    :value="$finance['invoice_payment_terms_days']" required />
                            </div>
                            <div class="col-12">
                                <x-form.input name="quotation_approval_threshold" label="Quotation approval threshold" inputmode="decimal"
                                    :value="$finance['quotation_approval_threshold']"
                                    help="Quotations with a total above this amount need approval. Leave empty if quotations never need approval." />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</x-layouts::app>
