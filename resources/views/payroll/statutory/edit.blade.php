@inject('settings', App\Services\System\SettingsService::class)
<x-layouts::app :title="'Edit · '.$rate->code->label()">
    <x-page-header :title="'Edit · '.$rate->code->label()" :subtitle="'Effective from '.$settings->formatDate($rate->effective_from)"
        :breadcrumbs="['Payroll' => route('payroll-runs.index'), 'Statutory rates' => route('statutory-rates.index'), 'Edit' => null]" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info small">Correct mistakes here. For a change in the law, add a new version instead. Changing the rules clears the verification.</div>
                    <form method="POST" action="{{ route('statutory-rates.update', $rate) }}" novalidate>
                        @csrf
                        @method('PUT')
                        @include('payroll.statutory._form')
                        <button type="submit" class="btn btn-primary">Save changes</button>
                        <a href="{{ route('statutory-rates.index') }}" class="btn btn-link">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
