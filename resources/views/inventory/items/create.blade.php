<x-layouts::app title="New item">
    <x-page-header title="New item" :breadcrumbs="['Items' => route('items.index'), 'New' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('items.store') }}" novalidate>
                @csrf
                @include('inventory.items._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create item</button>
                    <a href="{{ route('items.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
