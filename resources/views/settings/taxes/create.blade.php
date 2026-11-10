<x-layouts::app title="New tax rate">
    <x-page-header title="New tax rate"
        :breadcrumbs="['Settings' => route('settings.index'), 'Tax rates' => route('settings.taxes.index'), 'New' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('settings.taxes.store') }}" novalidate>
                @csrf
                @include('settings.taxes._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create tax rate</button>
                    <a href="{{ route('settings.taxes.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
