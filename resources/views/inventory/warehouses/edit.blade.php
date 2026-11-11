<x-layouts::app :title="'Edit '.$warehouse->name">
    <x-page-header :title="'Edit '.$warehouse->name" :breadcrumbs="['Warehouses' => route('warehouses.index'), $warehouse->name => null]" />

    <div class="card" style="max-width: 48rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('warehouses.update', $warehouse) }}" novalidate>
                @csrf
                @method('PUT')
                @include('inventory.warehouses._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('warehouses.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
