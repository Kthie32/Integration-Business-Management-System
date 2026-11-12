<x-layouts::app :title="'New version · '.$rate->code->label()">
    <x-page-header :title="'New version · '.$rate->code->label()" subtitle="The current version ends the day before the new one starts."
        :breadcrumbs="['Payroll' => route('payroll-runs.index'), 'Statutory rates' => route('statutory-rates.index'), 'New version' => null]" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('statutory-rates.store') }}" novalidate>
                        @csrf
                        <input type="hidden" name="code" value="{{ $rate->code->value }}">
                        <div class="row">
                            <div class="col-sm-6">
                                <x-form.input name="effective_from" label="Effective from" type="date" :value="$rate->effective_from?->toDateString()" required />
                            </div>
                        </div>
                        @include('payroll.statutory._form')
                        <button type="submit" class="btn btn-primary">Add version</button>
                        <a href="{{ route('statutory-rates.index') }}" class="btn btn-link">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
