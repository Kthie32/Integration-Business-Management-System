<x-layouts::app title="New warehouse">
    <x-page-header title="New warehouse" :breadcrumbs="['Warehouses' => route('warehouses.index'), 'New' => null]" />

    <div class="card" style="max-width: 48rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('warehouses.store') }}" novalidate>
                @csrf
                @include('inventory.warehouses._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create warehouse</button>
                    <a href="{{ route('warehouses.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
