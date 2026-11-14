<x-layouts::app :title="'Edit '.$payComponent->name">
    <x-page-header :title="'Edit '.$payComponent->name"
        :breadcrumbs="['Payroll' => route('payroll-runs.index'), 'Pay components' => route('pay-components.index'), $payComponent->name => null]" />

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    @if ($payComponent->isInUse())
                        <div class="alert alert-info small">This component is in use, so its type and calculation cannot change. Payslips already calculated keep their amounts.</div>
                    @endif
                    <form method="POST" action="{{ route('pay-components.update', $payComponent) }}" novalidate>
                        @csrf
                        @method('PUT')
                        @include('payroll.components._form')
                        <button type="submit" class="btn btn-primary">Save changes</button>
                        <a href="{{ route('pay-components.index') }}" class="btn btn-link">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
