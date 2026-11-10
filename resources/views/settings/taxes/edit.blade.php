<x-layouts::app title="Edit tax rate">
    <x-page-header :title="'Edit '.$taxRate->name"
        :breadcrumbs="['Settings' => route('settings.index'), 'Tax rates' => route('settings.taxes.index'), $taxRate->name => null]" />

    <div class="card">
        <div class="card-body">
            <p class="small text-secondary">Changing a rate only affects documents created afterwards; existing documents keep the rate they were created with.</p>

            <form method="POST" action="{{ route('settings.taxes.update', $taxRate) }}" novalidate>
                @csrf
                @method('PUT')
                @include('settings.taxes._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('settings.taxes.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
