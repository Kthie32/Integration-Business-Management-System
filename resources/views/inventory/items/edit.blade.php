<x-layouts::app :title="'Edit '.$item->name">
    <x-page-header :title="'Edit '.$item->name" :subtitle="$item->code"
        :breadcrumbs="['Items' => route('items.index'), $item->name => route('items.show', $item), 'Edit' => null]" />

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('items.update', $item) }}" novalidate>
                @csrf
                @method('PUT')
                @include('inventory.items._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('items.show', $item) }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
