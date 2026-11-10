<x-layouts::app title="New supplier">
    <x-page-header title="New supplier" :breadcrumbs="['Suppliers' => route('suppliers.index'), 'New' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('suppliers.store') }}" novalidate>
                @csrf
                @include('suppliers._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create supplier</button>
                    <a href="{{ route('suppliers.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
