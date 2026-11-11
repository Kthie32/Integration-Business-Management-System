<x-layouts::app :title="'Edit '.$category->name">
    <x-page-header :title="'Edit '.$category->name" :breadcrumbs="['Item categories' => route('item-categories.index'), $category->name => null]" />

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            <form method="POST" action="{{ route('item-categories.update', $category) }}" novalidate>
                @csrf
                @method('PUT')
                @include('inventory.categories._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('item-categories.index') }}" class="btn btn-link text-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
